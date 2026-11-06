<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
requireAdmin();

$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid order ID'); }
$q=db()->prepare('SELECT * FROM rental_orders WHERE id=?');
$q->execute([$id]); $order=$q->fetch();
if (!$order) { http_response_code(404); exit('Order not found'); }
if ($order['status'] !== 'reserved') { http_response_code(409); exit('Pickup sheets are available for reserved orders only.'); }
$q=db()->prepare('SELECT product_name, quantity, product_id FROM rental_order_items WHERE order_id=? ORDER BY product_name,id');
$q->execute([$id]); $items=$q->fetchAll();

// Small dependency-free PDF writer using standard PDF Helvetica.
// Output is intentionally plain, print-friendly, and paginated.
function pdfString(string $value): string {
    $value=preg_replace('/[\r\n\t]+/u',' ', $value) ?? '';
    $value=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value) ?: '';
    $value=preg_replace('/[\x00-\x1F\x7F]/','', $value) ?? '';
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);
}
function pdfWrap(string $text,int $limit=78): array {
    $lines=[];
    foreach (preg_split('/\R/u',$text) ?: [''] as $paragraph) {
        $words=preg_split('/\s+/u',trim($paragraph)) ?: [];
        $line='';
        foreach ($words as $word) {
            if ($word==='') continue;
            $candidate=$line===''?$word:$line.' '.$word;
            if (mb_strlen($candidate,'UTF-8')>$limit && $line!=='') { $lines[]=$line; $line=$word; }
            else $line=$candidate;
            while(mb_strlen($line,'UTF-8')>$limit) {
                $lines[]=mb_substr($line,0,$limit,'UTF-8');
                $line=mb_substr($line,$limit,null,'UTF-8');
            }
        }
        $lines[]=$line;
    }
    return $lines;
}
$pages=[];$commands=[];$y=755;
function lineText(string $text,int $size=11,bool $bold=false,int $indent=48): void {
    global $commands,$y,$pages;
    if ($y < 60) { $pages[]=$commands; $commands=[]; $y=755; }
    $font=$bold?'F2':'F1';
    $commands[]="BT /$font $size Tf 1 0 0 1 $indent $y Tm (".pdfString($text).") Tj ET";
    $y-=$size+8;
}
function rule(): void {
    global $commands,$y;
    $commands[]="0.7 w 48 $y m 564 $y l S";$y-=15;
}
lineText('PICKUP SHEET',20,true);
lineText('Order: '.$order['order_number'].'    Status: RESERVED',11,true);
lineText('Pickup: '.$order['pickup_date'].'    Due back: '.$order['return_date'],11);
rule();
lineText('CUSTOMER INFORMATION',12,true);
foreach (pdfWrap('Name: '.$order['customer_name']) as $line) lineText($line);
foreach (pdfWrap('Email: '.$order['customer_email']) as $line) lineText($line);
foreach (pdfWrap('Phone: '.$order['customer_phone']) as $line) lineText($line);
if (trim((string)$order['customer_notes'])!=='') {
    lineText('Customer notes:',11,true);
    foreach(pdfWrap((string)$order['customer_notes']) as $line) lineText($line,10,false,58);
}
rule();
lineText('ITEMS TO PICK FROM SHELVES',12,true);
lineText('Check each box as items are gathered:',10);
$units=0;
foreach ($items as $item) {
    $units+=(int)$item['quantity'];
    $text='[  ]  '.(int)$item['quantity'].' x '.$item['product_name'];
    if ($item['product_id']!==null) $text.='  (Product #'.$item['product_id'].')';
    $lines=pdfWrap($text,72);
    foreach($lines as $n=>$line) lineText($line,11,$n===0,$n===0?48:78);
}
rule();
lineText('Total units to prepare: '.$units,11,true);
lineText('Prepared by: ________________________    Date: _______________',10);
lineText('Customer pickup signature: ___________________________________',10);
lineText('Pickup confirmed by: __________________    Date: _______________',10);
$pages[]=$commands;
$objects=[];
$add=function(string $value) use (&$objects): int { $objects[]=$value;return count($objects); };
$catalog=$add('');$pagesRoot=$add('');
$font1=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');
$font2=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>');
$pageRefs=[];
foreach ($pages as $content) {
    $stream=implode("\n",$content)."\n";
    $contentRef=$add('<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream");
    $pageRefs[]=$add("<< /Type /Page /Parent $pagesRoot 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 $font1 0 R /F2 $font2 0 R >> >> /Contents $contentRef 0 R >>");
}
$objects[$pagesRoot-1]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($r)=>"$r 0 R",$pageRefs)).'] /Count '.count($pageRefs).' >>';
$objects[$catalog-1]="<< /Type /Catalog /Pages $pagesRoot 0 R >>";
$bytes="%PDF-1.4\n";$offsets=[0];
foreach ($objects as $i=>$object) { $offsets[]=strlen($bytes);$bytes.=($i+1)." 0 obj\n".$object."\nendobj\n"; }
$xref=strlen($bytes);$bytes.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
foreach(array_slice($offsets,1) as $offset) $bytes.=sprintf("%010d 00000 n \n",$offset);
$bytes.="trailer\n<< /Size ".(count($objects)+1)." /Root $catalog 0 R >>\nstartxref\n$xref\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="pickup-'.preg_replace('/[^A-Za-z0-9_-]/','-',(string)$order['order_number']).'.pdf"');
header('Content-Length: '.strlen($bytes));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $bytes;
