<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Composer\Autoload\pdfcrowd;

require_once 'user/data/SQLiteConnection.php';
use Grav\db\SQLiteConnection;



/**
 * Class AiSummariesPlugin
 * @package Grav\Plugin
 */
class AiSummariesPlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['onPluginsInitialized', 0]
            ]
        ];
    }
    //this really should be cron or admin button
    //for now were just going to do it load when plugin activated

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
    
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }


$SQLiteConnection = new Aisummaries\SQLiteConnection();
$pdo = $SQLiteConnection->connect();

$stm = $pdo->query("SELECT * FROM links WHERE category = 'test'"); //

   if(!$pdo) {
      echo $db->lastErrorMsg();
   } else {
      $this->grav['log']->info("Opened database successfully");
   }

      $articles = []; 
      while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
          $articles[] = [
              'title' => $row['title'],
              'link' => $row['links']
          ];
      }

      $url = $articles[5]['link'];//TODO loop through the last articles not just #5

        // download a pdf of the article links using pdfcrowd API
    $client = new \Pdfcrowd\HtmlToPdfClient("yehudaclinton", "fca7cb******22dfa1f0");
    $client->convertUrlToFile($url, "user/data/pdf/".substr($articles[5]['title'], 0, 3)."test.pdf");

$filePath = 'user/data/pdf/'.substr($articles[5]['title'], 0, 3).'test.pdf';
$this->grav['log']->info('downloaded-pdf. now uploading '.$filePath);
$apiKey = 'sec_chNRK31*****S3yKFnLMsnL'; //chatpdf

$ch = curl_init(); //upload pdf
curl_setopt($ch, CURLOPT_URL, 'https://api.chatpdf.com/v1/sources/add-file');
curl_setopt($ch, CURLOPT_POST, 1);
$headers = array();
$headers[] = 'X-Api-Key: ' . $apiKey;
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Attach the file to the request.
$post = array('file'=> new \CURLFile($filePath, 'application/pdf'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);

$resultDecoded = json_decode($result, true); // Parse JSON to associative array
if(!isset($resultDecoded['sourceId'])) {
    $this->grav['log']->error('No sourceId found in response: ' . $result);
    return;
}
$sourceId = $resultDecoded['sourceId'];

if(curl_errno($ch)){
    $this->grav['log']->error('Error:' . curl_error($ch));
}

curl_close ($ch);
$this->grav['log']->info('uploaded pdf '.$sourceId.' now getting summary');


$ch = curl_init(); //summarize pdf
curl_setopt($ch, CURLOPT_URL, 'https://api.chatpdf.com/v1/chats/message');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
$headers = array();
$headers[] = 'X-Api-Key: ' . $apiKey;
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Construct the request body payload.
$data = array(
  'sourceId' => $sourceId,
  'messages' => array(
    array(
      'role' => 'user',
      'content' => 'summerize the articles main points?'
    )
  )
);

curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$result = curl_exec($ch);
$resultDecoded = json_decode($result, true);
if(curl_errno($ch)){
    $this->grav['log']->error('Error:' . curl_error($ch));
}
curl_close ($ch);

  // save summary to summaries page
  $content = file_get_contents("user/pages/04.summaries/default.md");
  $pos = strpos($content, "---", strpos($content, "---",3)+strlen("---"));
  $newcontnent = substr_replace($content,"---  \n  \n**[".($articles[5]['title'])."](".$articles[5]['link'].")** ".$resultDecoded['content']."  \n  \n",$pos,3);
  //rewrite the whole page including new summary
  $rewrite = file_put_contents("user/pages/04.summaries/default.md", $newcontnent);


        // Enable the main events we are interested in
        $this->enable([
            // Put your main events here
        ]);
    }
}