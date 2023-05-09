<?php

$metadata="/data/CORPORA/USPDATRO/CORPUS/raw/USPDATRO_Metadata - Dataset.csv";
$outPath="/data/CORPORA/USPDATRO/CORPUS/processed";
$runFFMPEG=false;


require_once("GETID3/getID3-master/getid3/getid3.php");

@mkdir($outPath);
@mkdir("$outPath/text");
@mkdir("$outPath/audio");

if($runFFMPEG===false){
    echo "Run FFMPEG is disabled! Only stats will be computed!\n\n";
}

function readMetadata($meta){
    $data=[];

    $fp=fopen($meta,"r");
    if($fp===false){
        die("Cannot open metadata [$meta]");
    }
    $first=true;
    $lnum=0;
    while(!feof($fp)){
        $line=fgetcsv($fp,0,",","\"");
        $lnum++;
        if($line===false)break;
        if(count($line)<8){echo "Bad line [$lnum]\n";continue;}

        if($first){$first=false;continue;}

        $data[$line[0]]=[
            "annotator" => $line[1],
            "platform" => $line[2],
            "url" => $line[3],
            "duration" => $line[4],
            "durationTranscribed" => $line[5],
            "license" => $line[6],
            "type" => $line[7],
            "mos" => $line[8],
            "spk" => []
        ];

        for($i=9;$i<count($line) && $line[$i]!="";$i+=2){
            $data[$line[0]]["spk"][]=["sex"=>$line[$i],"age"=>$line[$i+1]];
        }

        if(count($data[$line[0]]["spk"])==0){
            echo "No speakers on line [$lnum]\n";
        }

    }

    return $data;
}



function startsWithIgnoreCase( $haystack, $needle ) {
    return startsWith(strtolower($haystack),strtolower($needle));
}


function startsWith( $haystack, $needle ) {
     $length = strlen( $needle );
     return substr( $haystack, 0, $length ) === $needle;
}
function endsWith( $haystack, $needle ) {
    $length = strlen( $needle );
    if( !$length ) {
        return true;
    }
    return substr( $haystack, -$length ) === $needle;
}

function processFolder($dir){
    if(!is_dir($dir))die("Invalid folder [$dir]");

    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            $path="$dir/$file";
            if(is_file($path) && endsWith($path,".csv")){
                processFile($dir,$file);
            }
        }
        closedir($dh);
    }
}

function getTimeStr($tstr){
    $t=floatval(str_replace(",",".",$tstr));
    $total_sec=intval($t/1000);
    $ms=intval($t-$total_sec*1000);
    $s=$total_sec%60;
    $total_m=intval($total_sec/60);
    $m=$total_m%60;
    $h=intval($total_m/60);
    return sprintf("%02d:%02d:%02d.%03d",$h,$m,$s,$ms);
}

$stats=[];
$corpusMeta=[];
function addStat($name,$val){
    global $stats;

    if(!isset($stats[$name]))$stats[$name]=$val;
    else $stats[$name]+=$val;
}

function processFile($dir,$file){
    global $meta,$outPath,$runFFMPEG,$corpusMeta;
    $id=substr($file,0,strlen($file)-4);
    echo "Processing [$id]\n";

    if(!isset($meta[$id])){
        die("NO METADATA");
    }

    $getID3 = new getID3();
    $fInfo = $getID3->analyze("$dir/$id.mp4");

    $stat_file_add=[];

    $fp=fopen("$dir/$file","r");
    $lnum=-1;
    $lastTo=false;
    while(!feof($fp)){
        $lnum++;
        $line=fgetcsv($fp,0,";","\"");
        if($line===false)break;

        if(count($line)!=4){
            die("Invalid line [$lnum]");
        }

        if($lnum==0)continue;

        $from=getTimeStr($line[1]);
        $to=getTimeStr($line[2]);
        $lastTo=$to;
        $segment_ms=floatval(str_replace(",",".",$line[2]))-floatval(str_replace(",",".",$line[1]));

        $text=$line[3];
        $text=preg_replace("/[\n\r\t]+/"," ",$text);
        $text=preg_replace("/[ ]+/"," ",$text);
        $text=preg_replace('/["”]([a-zA-ZăîâșțĂÎÂȘȚ0-9])/u','„$1',$text);
        $text=preg_replace('/([a-zA-ZăîâșțĂÎÂȘȚ0-9])["„]/u','$1”',$text);

        $spk=false;
        if(!startsWithIgnoreCase($text,"SPK")){
            if(count($meta[$id]["spk"])>1){
                echo "SPK not set [$lnum]\n"; die();
            }
            $spk=1;
        }else {
            $spk_text=explode(" ",$text,2);
            $spk=intval(substr($spk_text[0],3));
            $text=$spk_text[1];
        }

        if(!endsWith($text,".") && !endsWith($text,"!") && !endsWith($text,"?"))$text.=".";

        $spk--;

        addStat("ms_total",$segment_ms);
        addStat("ms_".$meta[$id]["spk"][$spk]["sex"],$segment_ms);
        addStat("num_seg_".$meta[$id]["spk"][$spk]["sex"],1);
        addStat("ms_".$meta[$id]["spk"][$spk]["age"],$segment_ms);
        addStat("num_seg_".$meta[$id]["spk"][$spk]["age"],1);
        addStat("ms_".$meta[$id]["spk"][$spk]["sex"]."_".$meta[$id]["spk"][$spk]["age"],$segment_ms);
        addStat("num_seg_".$meta[$id]["spk"][$spk]["sex"]."_".$meta[$id]["spk"][$spk]["age"],1);
        addStat("ms_mos_".$meta[$id]["mos"],$segment_ms);
        addStat("num_seg_mos_".$meta[$id]["mos"],1);

        $stat_file_add[$meta[$id]["spk"][$spk]["sex"]."_".$meta[$id]["spk"][$spk]["age"]]=1;
        $stat_file_add[$meta[$id]["spk"][$spk]["sex"]]=1;
        $stat_file_add[$meta[$id]["spk"][$spk]["age"]]=1;

        $corpusMeta[]=[
            "id"=>"${id}_${lnum}",
            "platform"=>$meta[$id]["platform"],
            "url"=>$meta[$id]["url"],
            "license"=>$meta[$id]["license"],
            "type"=>$meta[$id]["type"],
            "file_num_speakers"=>count($meta[$id]["spk"]),
            "file_MOS"=>$meta[$id]["mos"],
            "file_duration_ms"=>$fInfo["playtime_seconds"]*1000,
            "file_duration"=>$meta[$id]["duration"],
            "file_duration_transcribed"=>$meta[$id]["durationTranscribed"],
            "segment_from_ms"=>str_replace(",",".",$line[1]),
            "segment_from"=>$from,
            "segment_to_ms"=>str_replace(",",".",$line[2]),
            "segment_to"=>$to,
            "segment_duration_ms"=>$segment_ms,
            "segment_duration"=>getTimeStr($segment_ms),
            "segment_speaker_num"=>($spk+1),
            "segment_speaker_sex"=>$meta[$id]["spk"][$spk]["sex"],
            "segment_speaker_age"=>$meta[$id]["spk"][$spk]["age"],
            "segment_text_len"=>mb_strlen($text),
            "segment_text_letters"=>mb_strlen(preg_replace("/[^a-zA-ZăîâșțĂÎÂȘȚ]/u","",$text)),
            "segment_text_words"=>count(explode(" ",trim(preg_replace("/[ ]+/"," ",preg_replace("/[^a-zA-ZăîâșțĂÎÂȘȚ]/u"," ",$text))))),
        ];

        if($runFFMPEG){
            $fname="${outPath}/text/${id}_${lnum}";
            file_put_contents("$fname.txt",$text);

            $fname="${outPath}/audio/${id}_${lnum}";
            passthru("/data/programs/ffmpeg_build/bin/ffmpeg -y -ss $from -to $to -i $dir/$id.mp4 -vn -acodec pcm_s16le -ac 1 -ar 16000  ${fname}.wav");
        }

    }

    foreach($stat_file_add as $k=>$v)addStat("num_file_$k",$v);

    echo "Last segment end time: $lastTo\n";
}

echo "Reading metadata\n";
$meta=readMetadata($metadata);
//var_dump($meta);
echo "Processing corpus files\n";
processFolder("../raw/VASILE");
processFolder("../raw/ELENA");
processFolder("../raw/VERGI");
processFolder("../raw/RADU");


foreach($stats as $k=>$v){
    if(startsWith($k,"ms_"))
        echo str_pad($k,25).str_pad($v,20).getTimeStr("$v")."\n";
    else 
        echo str_pad($k,25).str_pad(" ",20)."$v\n";
}

$fout=fopen("$outPath/metadata.csv","w");
$header=array_keys($corpusMeta[0]);
fwrite($fout,implode(";",$header)."\n");
foreach($corpusMeta as $entry){
    for($i=0;$i<count($header);$i++){
        if($i>0)fwrite($fout,";");
        fwrite($fout,$entry[$header[$i]]);
    }
    fwrite($fout,"\n");
}
fclose($fout);


$fout=fopen("stats.csv","w");
fwrite($fout,"Indicator;Value Raw;Value Formatted\n");
foreach($stats as $k=>$v){
    if(startsWith($k,"ms_"))
        fwrite($fout,"$k;$v;".getTimeStr("$v")."\n");
    else 
        fwrite($fout,"$k;;$v\n");
}
fclose($fout);

