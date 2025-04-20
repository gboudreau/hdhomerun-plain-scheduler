<?php
//
// tvtv2xmltv Guide Data
// https://gist.github.com/idolpx/c82747bb740c303f56ad8a1e8f17d575
// Author: Jaime Idolpx (jaime@idolpx.com)
//
// - This script will extract guide data from "tvtv.us" and produce an "XmlTV" data file
// - Set the options for the guide data you want to extract below
// - Host this on a php enabled web server
// - Configure your TV Guide software to use it as a data source (Jellyfin in my case)
//
// https://www.tvtv.us/
// http://wiki.xmltv.org/index.php/XMLTVFormat
// https://www.xmltvlistings.com/help/api/xmltv
//


$timezone = "America/Toronto";  // Set to your local timezone
$lineUpID = "CAN-OTAH3P0A1";    // Set this to ID of the Line Up data you want to extract
$days     = 8;                  // Number of days worth of guide data to collect (8 days max)

//////////////////////////////////////////////////////////////////////////////////////////////////

//// Setup filename for download
//$fileDate = date ( "Ymd" );
//header("Content-disposition: attachment; filename=xmltv.".$fileDate.".xml");
//header("Content-type: text/xml");

// Build XMLTV data
$url = "http". ( !empty ( $_SERVER['HTTPS'] ) ? "s" : "" )."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$now = strtotime ( "now" );
$startTime = date ( 'Y-m-d\T00:00:00.000\Z', $now );

echo("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n");
echo("<!DOCTYPE tv SYSTEM \"xmltv.dtd\">\r\n");
echo("<tv date=\"".$startTime."\" source-info-url=\"".$url."\" source-info-name=\"tvtv2xmltv\">\r\n");

// GET lineup data
$lineup_url = "https://www.tvtv.us/api/v1/lineup/$lineUpID/channels";
$json = file_get_contents( $lineup_url );
$lineup_data = json_decode( $json, true );

$channels = "";
foreach ( $lineup_data as &$channel )
{
    // Build channel query string
    $channels .= $channel["stationId"].",";

    // Channel data
    echo("<channel id=\"".$channel["channelNumber"]."\">");
    echo("<display-name>".$channel["stationCallSign"]."</display-name>");
    echo("<display-name>".$channel["channelNumber"]."</display-name>");
    echo("<icon src=\"https://www.tvtv.us".$channel["logo"]."\" />");
    echo("</channel>\r\n");
    @ob_flush(); flush();
}

// Get max 8 days of guide data starting today
if ( $days > 8 ) $days = 8;
for ( $day = 0; $day < $days; $day++)
{
    // GET guide data
    $now = strtotime ( "now + ".$day." day" );
    $end = strtotime ("now + ".($day + 1)." day" );
    $startTime = date ( 'Y-m-d\T04:00:00.000\Z', $now ); //"2023-05-23T04:00:00.000Z";
    $endTime = date ( 'Y-m-d\T03:59:00.000\Z', $end ); //"2023-05-24T03:59:00.000Z";
    $listing_url = "https://www.tvtv.us/api/v1/lineup/".$lineUpID."/grid/".$startTime."/".$endTime."/".$channels;
    $json = file_get_contents( $listing_url );
    $listing_data = json_decode( $json, true );

    $index = 0;
    foreach ( $lineup_data as &$channel )
    {
        // Program Data
        foreach ( $listing_data[$index] as &$program )
        {
            $programId = htmlspecialchars ( $program['programId'], ENT_XML1, 'UTF-8' );
            $title = htmlspecialchars ( $program['title'], ENT_XML1, 'UTF-8' );
            $subtitle = @htmlspecialchars ( $program['subtitle'], ENT_XML1, 'UTF-8' );
            $flags = implode ( ", ", $program['flags'] );
            $type = htmlspecialchars ( $program['type'], ENT_XML1, 'UTF-8' );
            $startTime = htmlspecialchars ( $program['startTime'], ENT_XML1, 'UTF-8' );
            $start = htmlspecialchars ( $program['start'], ENT_XML1, 'UTF-8' );
            $duration = htmlspecialchars ( $program['duration'], ENT_XML1, 'UTF-8' );
            $runTime = htmlspecialchars ( $program['runTime'], ENT_XML1, 'UTF-8' );

            $tStart = new DateTime($startTime);
            $tStart->setTimeZone(new DateTimeZone($timezone));
            $startTime = $tStart->format("YmdHis O");
            $tStart->add(new DateInterval('PT'.$program['runTime'].'M'));
            $endTime = $tStart->format("YmdHis O");

            echo("<programme start=\"".$startTime."\" stop=\"".$endTime."\" duration=\"".$duration."\" channel=\"".$channel["channelNumber"]."\">");
            echo("<title lang=\"en\">".$title."</title>");
            echo("<sub-title lang=\"en\">".$subtitle."</sub-title>");

            if ( $type == "M" )
                echo("<category lang=\"en\">movie</category>");

            if ( $type == "N" )
                echo("<category lang=\"en\">news</category>");

            if ( $type == "S" )
                echo("<category lang=\"en\">sports</category>");

            if ( strstr($flags, "EI") )
                echo("<category lang=\"en\">kids</category>");

            if ( strstr($flags, "HD") )
            {
                echo("<video>");
                echo("<quality>HDTV</quality>");
                echo("</video>");
            }

            if ( strstr($flags, "Stereo") )
            {
                echo("<audio>");
                echo("<stereo>stereo</stereo>");
                echo("</audio>");
            }

            if ( strstr($flags, "New") )
            {
                echo("<new />");
            }

            echo("</programme>\r\n");
            @ob_flush(); flush();
        }

        $index++;
    }
}

echo("</tv>");