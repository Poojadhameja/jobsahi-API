<?php
require_once '../cors.php';
require_once '../db.php';

$upload_dir = __DIR__ . '/../uploads/institute_certificate/';
$relative_path = '/uploads/institute_certificate/';

function pdf_escape($text){
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    echo json_encode(["status"=>false,"message"=>"Only POST allowed"]);
    exit;
}

$decoded = authenticateJWT(['admin','institute']);
$role = strtolower($decoded['role']);
$user_id = intval($decoded['user_id']);

$input=json_decode(file_get_contents("php://input"),true);
$student_id=intval($input['student_id']??0);
$course_id=intval($input['course_id']??0);
$template_id=intval($input['template_id']??0);
$issue_date=$input['issue_date']??date('Y-m-d');

$created_at=date('Y-m-d H:i:s');
$admin_action='approved';

if($student_id<=0||$course_id<=0||$template_id<=0){
    echo json_encode(["status"=>false,"message"=>"Missing IDs"]);
    exit;
}

// prevent duplicate
$chk=$conn->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=? LIMIT 1");
$chk->bind_param("ii",$student_id,$course_id);
$chk->execute();
if($chk->get_result()->num_rows>0){
    echo json_encode(["status"=>false,"message"=>"Certificate already exists"]);
    exit;
}
$chk->close();

// detect institute
$institute_id=0;
if($role==='institute'){
    $s=$conn->prepare("SELECT id FROM institute_profiles WHERE user_id=? AND deleted_at IS NULL");
    $s->bind_param("i",$user_id);
    $s->execute();
    $r=$s->get_result()->fetch_assoc();
    if(!$r){echo json_encode(["status"=>false,"message"=>"Institute not found"]);exit;}
    $institute_id=intval($r['id']);
    $s->close();
}

// load template
if($role==='admin'){
    $tpl=$conn->prepare("SELECT * FROM certificate_templates WHERE id=? AND is_active=1 AND deleted_at IS NULL");
    $tpl->bind_param("i",$template_id);
}else{
    $tpl=$conn->prepare("SELECT * FROM certificate_templates WHERE id=? AND institute_id=? AND is_active=1 AND deleted_at IS NULL");
    $tpl->bind_param("ii",$template_id,$institute_id);
}
$tpl->execute();
$template=$tpl->get_result()->fetch_assoc();
$tpl->close();

if(!$template){
    echo json_encode(["status"=>false,"message"=>"Template not found"]);
    exit;
}

// R2 media URLs - download temporarily for PDF generation
function getMedia($file) {
    if(empty($file)) return ["url"=>null,"local"=>null];
    
    // If it's a URL (R2), download temporarily for PDF generation
    if(filter_var($file, FILTER_VALIDATE_URL)) {
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_img_');
        $imageData = @file_get_contents($file);
        
        if($imageData !== false && file_put_contents($tempFile, $imageData)) {
            return ["url"=>$file,"local"=>$tempFile];
        } else {
            @unlink($tempFile);
            return ["url"=>$file,"local"=>null];
        }
    }
    
    // Return as-is (shouldn't happen if all files are in R2)
    return ["url"=>$file,"local"=>null];
}

// images (R2 URLs - downloaded temporarily for PDF)
$logo=getMedia($template['logo']);
$seal=getMedia($template['seal']);
$sign=getMedia($template['signature']);

// student, course details
$info=$conn->prepare("
SELECT u.user_name AS student_name, co.title AS course_title
FROM student_profiles s
JOIN users u ON u.id=s.user_id
JOIN courses co ON co.id=?
WHERE s.id=?
");
$info->bind_param("ii",$course_id,$student_id);
$info->execute();
$details=$info->get_result()->fetch_assoc();
$info->close();

if(!$details){
    echo json_encode(["status"=>false,"message"=>"Invalid student or course"]);
    exit;
}

// TEXT content - USE TEMPLATE DESCRIPTION AUTOMATICALLY
$title      = pdf_escape($template['template_name']);
$description = pdf_escape($template['description']); // Automatically use template description
$sname      = pdf_escape($details['student_name']);
$course     = pdf_escape($details['course_title']);
$dateStr    = "Date: ".date("d/m/Y",strtotime($issue_date));

// SMART LINE BREAKING - Split description into 3 lines automatically
function splitIntoLines($text, $maxCharsPerLine = 90) {
    $words = explode(' ', $text);
    $lines = [];
    $currentLine = '';
    
    foreach($words as $word) {
        $testLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;
        if(strlen($testLine) <= $maxCharsPerLine) {
            $currentLine = $testLine;
        } else {
            if(!empty($currentLine)) {
                $lines[] = $currentLine;
            }
            $currentLine = $word;
        }
    }
    if(!empty($currentLine)) {
        $lines[] = $currentLine;
    }
    
    // Pad to 3 lines
    while(count($lines) < 3) {
        $lines[] = '';
    }
    
    return array_slice($lines, 0, 3);
}

list($desc1, $desc2, $desc3) = splitIntoLines($description);
$desc1 = pdf_escape($desc1);
$desc2 = pdf_escape($desc2);
$desc3 = pdf_escape($desc3);

if(!is_dir($upload_dir))mkdir($upload_dir,0777,true);
$file_name  = "certificate"."_{$user_id}.pdf";
$file_path  = $upload_dir.$file_name;

// PNG to RGB converter WITHOUT GD library
function pngToRgb($file) {
    $data = file_get_contents($file);
    if(substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
    
    $pos = 8;
    $ihdr = null;
    $idat = '';
    $plte = null;
    $trns = null;
    
    // Parse PNG chunks
    while($pos < strlen($data)) {
        $len = unpack('N', substr($data, $pos, 4))[1];
        $type = substr($data, $pos + 4, 4);
        $chunk = substr($data, $pos + 8, $len);
        
        if($type === 'IHDR') {
            $ihdr = unpack('Nwidth/Nheight/Cbit/Ccolor/Ccompress/Cfilter/Cinterlace', $chunk);
        } elseif($type === 'IDAT') {
            $idat .= $chunk;
        } elseif($type === 'PLTE') {
            $plte = $chunk;
        } elseif($type === 'tRNS') {
            $trns = $chunk;
        } elseif($type === 'IEND') {
            break;
        }
        
        $pos += $len + 12;
    }
    
    if(!$ihdr || empty($idat)) return false;
    
    // Decompress image data
    $raw = @gzuncompress($idat);
    if($raw === false) {
        $raw = @gzinflate($idat);
        if($raw === false) return false;
    }
    
    $w = $ihdr['width'];
    $h = $ihdr['height'];
    $color = $ihdr['color'];
    $bit = $ihdr['bit'];
    
    // Calculate bytes per pixel
    $bpp = 1;
    if($color === 0) $bpp = ($bit == 16) ? 2 : 1;
    elseif($color === 2) $bpp = ($bit == 16) ? 6 : 3;
    elseif($color === 3) $bpp = 1;
    elseif($color === 4) $bpp = ($bit == 16) ? 4 : 2;
    elseif($color === 6) $bpp = ($bit == 16) ? 8 : 4;
    
    // Unfilter scanlines
    $rgb = '';
    $stride = $w * $bpp + 1;
    $prev = str_repeat("\x00", $w * $bpp);
    
    for($y = 0; $y < $h; $y++) {
        if($y * $stride >= strlen($raw)) break;
        
        $filter = ord($raw[$y * $stride]);
        $line = substr($raw, $y * $stride + 1, $w * $bpp);
        
        $unfiltered = '';
        for($i = 0; $i < strlen($line); $i++) {
            $x = ord($line[$i]);
            $a = ($i >= $bpp) ? ord($unfiltered[$i - $bpp]) : 0;
            $b = ord($prev[$i]);
            $c = ($i >= $bpp) ? ord($prev[$i - $bpp]) : 0;
            
            switch($filter) {
                case 0: $unfiltered .= chr($x); break;
                case 1: $unfiltered .= chr(($x + $a) % 256); break;
                case 2: $unfiltered .= chr(($x + $b) % 256); break;
                case 3: $unfiltered .= chr(($x + floor(($a + $b) / 2)) % 256); break;
                case 4:
                    $p = $a + $b - $c;
                    $pa = abs($p - $a);
                    $pb = abs($p - $b);
                    $pc = abs($p - $c);
                    if($pa <= $pb && $pa <= $pc) $pr = $a;
                    elseif($pb <= $pc) $pr = $b;
                    else $pr = $c;
                    $unfiltered .= chr(($x + $pr) % 256);
                    break;
                default: $unfiltered .= chr($x);
            }
        }
        
        $prev = $unfiltered;
        
        // Convert to RGB
        for($x = 0; $x < $w; $x++) {
            $offset = $x * $bpp;
            if($color === 2) {
                $rgb .= substr($unfiltered, $offset, 3);
            } elseif($color === 6) {
                $r = ord($unfiltered[$offset]);
                $g = ord($unfiltered[$offset + 1]);
                $b = ord($unfiltered[$offset + 2]);
                $a = ord($unfiltered[$offset + 3]);
                
                $alpha = $a / 255;
                $r = (int)(($r * $alpha) + (255 * (1 - $alpha)));
                $g = (int)(($g * $alpha) + (255 * (1 - $alpha)));
                $b = (int)(($b * $alpha) + (255 * (1 - $alpha)));
                
                $rgb .= chr($r) . chr($g) . chr($b);
            } elseif($color === 0 || $color === 4) {
                $gray = ord($unfiltered[$offset]);
                if($color === 4 && $bpp >= 2) {
                    $alpha = ord($unfiltered[$offset + 1]) / 255;
                    $gray = (int)(($gray * $alpha) + (255 * (1 - $alpha)));
                }
                $rgb .= chr($gray) . chr($gray) . chr($gray);
            } elseif($color === 3 && $plte) {
                $idx = ord($unfiltered[$offset]) * 3;
                if($idx + 2 < strlen($plte)) {
                    $rgb .= substr($plte, $idx, 3);
                } else {
                    $rgb .= "\xFF\xFF\xFF";
                }
            } else {
                $rgb .= "\xFF\xFF\xFF";
            }
        }
    }
    
    return ['width' => $w, 'height' => $h, 'data' => $rgb];
}

// Image embedding function
function makeImg($file, $id) {
    if(!$file || !file_exists($file)) return "";
    
    $info = @getimagesize($file);
    if(!$info) return "";
    
    list($w, $h, $type) = $info;
    
    if($type == IMAGETYPE_JPEG) {
        $raw = file_get_contents($file);
        $len = strlen($raw);
        
        return "
$id 0 obj
<< /Type /XObject /Subtype /Image /Width $w /Height $h
/ColorSpace /DeviceRGB /BitsPerComponent 8 /Length $len /Filter /DCTDecode >>
stream
$raw
endstream
endobj
";
    }
    elseif($type == IMAGETYPE_PNG) {
        $png = pngToRgb($file);
        if(!$png) return "";
        
        $w = $png['width'];
        $h = $png['height'];
        $compressed = gzcompress($png['data']);
        $len = strlen($compressed);
        
        return "
$id 0 obj
<< /Type /XObject /Subtype /Image /Width $w /Height $h
/ColorSpace /DeviceRGB /BitsPerComponent 8 /Length $len /Filter /FlateDecode >>
stream
$compressed
endstream
endobj
";
    }
    
    return "";
}

// Process images (from R2 URLs downloaded temporarily)
$logoObj = !empty($logo['local']) ? makeImg($logo['local'], 6) : "";
$sealObj = !empty($seal['local']) ? makeImg($seal['local'], 7) : "";
$signObj = !empty($sign['local']) ? makeImg($sign['local'], 8) : "";

$im1 = !empty($logoObj);
$im2 = !empty($sealObj);
$im3 = !empty($signObj);

// POSITIONING
$img = "";

if($im1){
    $img .= "q 50 0 0 50 60 480 cm /Im1 Do Q\n";
}

if($im2){
    $img .= "q 60 0 0 60 720 470 cm /Im2 Do Q\n";
}

if($im3){
    $img .= "q 80 0 0 25 700 100 cm /Im3 Do Q\n";
}

// Center text function
function cx($t,$s,$pageWidth=842){
    return ($pageWidth/2)-(strlen($t)*($s*0.5)/2);
}

// Build content stream
$content = "";

// Light blue background
$content .= "q 0.95 0.97 0.99 rg 50 70 742 475 re f Q\n";

// Blue border
$content .= "q 1.5 w 0.15 0.35 0.65 RG 52 72 738 471 re S Q\n";

// Title - centered at y=410
$content .= "BT /F3 32 Tf 0.1 0.35 0.65 rg ".cx($title,32)." 410 Td ($title) Tj ET\n";

// Description - 3 lines automatically from template
$content .= "BT /F1 9 Tf 0.2 0.4 0.6 rg ".cx($desc1,9)." 375 Td ($desc1) Tj ET\n";
$content .= "BT /F1 9 Tf 0.2 0.4 0.6 rg ".cx($desc2,9)." 362 Td ($desc2) Tj ET\n";
$content .= "BT /F1 9 Tf 0.2 0.4 0.6 rg ".cx($desc3,9)." 349 Td ($desc3) Tj ET\n";

// Student Name - centered at y=300
$content .= "BT /F2 26 Tf 0.1 0.35 0.65 rg ".cx($sname,26)." 300 Td ($sname) Tj ET\n";

// Course Title - centered at y=270
$content .= "BT /F1 16 Tf 0 0 0 rg ".cx($course,16)." 270 Td ($course) Tj ET\n";

// Date - bottom left
$content .= "BT /F1 10 Tf 0 0 0 rg 60 85 Td ($dateStr) Tj ET\n";

// Add images
$content .= $img;

// BUILD PDF
$pdf="%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595]
/Resources << /Font << /F1 5 0 R /F2 9 0 R /F3 10 0 R >> /XObject <<
";

if($im1)$pdf.="/Im1 6 0 R ";
if($im2)$pdf.="/Im2 7 0 R ";
if($im3)$pdf.="/Im3 8 0 R ";

$pdf.=">> >> /Contents 4 0 R >> endobj
4 0 obj << /Length ".strlen($content)." >> stream
$content
endstream endobj
5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj
";

if($im1)$pdf.=$logoObj;
if($im2)$pdf.=$sealObj;
if($im3)$pdf.=$signObj;

$pdf.="9 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj
10 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >> endobj
";

$xref=strlen($pdf);
$pdf.="
xref
0 11
0000000000 65535 f
trailer << /Size 11 /Root 1 0 R >>
startxref
$xref
%%EOF";

file_put_contents($file_path,$pdf);

// Cleanup temporary downloaded R2 images
if(!empty($logo['local']) && strpos($logo['local'], sys_get_temp_dir()) === 0) {
    @unlink($logo['local']);
}
if(!empty($seal['local']) && strpos($seal['local'], sys_get_temp_dir()) === 0) {
    @unlink($seal['local']);
}
if(!empty($sign['local']) && strpos($sign['local'], sys_get_temp_dir()) === 0) {
    @unlink($sign['local']);
}

// PUBLIC URL - Build protocol and host properly
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$public_url = $protocol . $host . "/jobsahi-API/api/uploads/institute_certificate/" . $file_name;

// SAVE DB RECORD
$stmt=$conn->prepare("
INSERT INTO certificates(student_id,course_id,certificate_template_id,file_url,issue_date,admin_action,created_at,modified_at)
VALUES(?,?,?,?,?,?,?,?)
");
$stmt->bind_param("iiisssss",$student_id,$course_id,$template_id,$public_url,$issue_date,$admin_action,$created_at,$created_at);
$stmt->execute();
$cid=$stmt->insert_id;
$stmt->close();

// FINAL RESPONSE
echo json_encode([
"status"=>true,
"message"=>"Certificate generated successfully",
"data"=>[
"certificate_id"=>"CERT-".date('Y')."-".str_pad($cid,3,"0",STR_PAD_LEFT),
"file_url"=>$public_url,
"template_name"=>$template['template_name'],
"student_name"=>$details['student_name'],
"course_title"=>$details['course_title'],
"description_used"=>$template['description']
]]);
exit;
?>