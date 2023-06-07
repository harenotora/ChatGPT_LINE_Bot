<?php
ini_set('display_errors', "Off");
ini_set('max_execution_time', 300);
require_once "vendor/autoload.php";
use Orhanerday\OpenAi\OpenAi;

$channel_id = "";
$channel_secret = "";
$bot_mid = "";
$fortune_frag = 0;
$image_frag = 0;
$accessToken = "";


//ユーザーからのメッセージ取得
$json_string = file_get_contents('php://input');
echo $json_string;
$json_object = json_decode($json_string);

$displayname ="";

//取得データ
$replyToken = $json_object->{"events"}[0]->{"replyToken"};        //返信用トークン
$to = $json_object->{"events"}[0]->{"source"}->{"userId"};
$source_type = $json_object->{"events"}[0]->{"source"}->{"type"};
$message_type = $json_object->{"events"}[0]->{"message"}->{"type"};    //メッセージタイプ
$message_text = $json_object->{"events"}[0]->{"message"}->{"text"};    //メッセージ内容

$user_profiles_url = curl_init("https://api.line.me/v2/bot/profile/" . urlencode($to));
curl_setopt($user_profiles_url, CURLOPT_RETURNTRANSFER, true);
curl_setopt($user_profiles_url, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charser=UTF-8',
        'Authorization: Bearer ' . $accessToken
));
$user_profiles_output = curl_exec($user_profiles_url);
$user_json_obj = json_decode($user_profiles_output);
$displayname = $user_json_obj->{"displayName"};
curl_close($user_profiles_url);

//メッセージタイプが「text」以外のときは何も返さず終了
if($message_type != "text") exit;

$retflag = 0;
$rval = rand(1,20);
$randmsg="";

if($rval==5) {
    $retflag = 1;
}
if ( preg_match("/ことら/", $message_text)) {
    $retflag = 1;
}
//ソースタイプが「group」以外のときは常に返信
if($source_type != "group") {
    $retflag = 1;
}

error_log("ことらの手前", 3, "./my-errors.log");
if ( $retflag == 1 ) {
    $q = preg_replace('/ことら/', '', $message_text);
    $q = preg_replace('/ことら、/', '', $message_text);
    $errmsg = "ごめん、質問が長すぎてよくわからなかったよ……。もうちょっと短い文章にしてくれる？";
    $ret = chatgpt($q);
    //$randmsg = preg_replace('/\<br.*\>/', '\n', $ret);
    $randmsg = str_replace("\n", "\n\r", $ret);
    //$randmsg = $ret;
    if ($randmsg)
    {
        $image_frag = 0;
        //$randmsg = str_replace(array("\r\n", "\r", "\n"), "", $randmsg);
    } else {
        $image_frag = 0;
        $randmsg = $errmsg;
    }
}

if ((preg_match("/かいて$/", $message_text) || preg_match("/書いて$/", $message_text) || preg_match("/描いて$/", $message_text) || preg_match("/作って$/", $message_text) || preg_match("/かいて！/", $message_text) || preg_match("/書いて！/", $message_text) || preg_match("/描いて！/", $message_text) || preg_match("/作って！/", $message_text) || preg_match("/つくって！/", $message_text)) && $retflag == 1 ){
    $q = preg_replace('/ことら/', '', $message_text);
    $q = preg_replace('/ことら、/', '', $message_text);
    $q = preg_replace('/の絵.*/', '', $q);
    $q = preg_replace('/のイラスト.*/', '', $q);
    $q = preg_replace('/の画像.*/', '', $q);
    $q = preg_replace('/の写真.*/', '', $q);
    $q = preg_replace('/写真.*/', '', $q);
    $q = preg_replace('/絵.*/', '', $q);
    $q = preg_replace('/イラスト.*/', '', $q);
    $q = preg_replace('/画像.*/', '', $q);
    $q = preg_replace('/書いて.*/', '', $q);
    $q = preg_replace('/描いて.*/', '', $q);
    $q = preg_replace('/かいて.*/', '', $q);
    $q = preg_replace('/作って.*/', '', $q);
    $q = preg_replace('/つくって.*/', '', $q);

    $errmsg = "うまく描けなかったよ……";

    if(preg_match("/イラスト/", $message_text)) {
        $q = $q . ",studio ghibli style";
    }
    if(preg_match("/写真/", $message_text)) {
        $q = $q . ",High quality photo";
    }
    $randmsg = dall_e($q);
    //$randmsg = $q;
    if ($randmsg)
    {
        $fortune_frag = 1;
        $image_frag = 1;
        //$fortune_frag = 0;
        //$image_frag = 0;
        $retflag = 1;
    } else {
        $fortune_frag = 0;
        $image_frag = 0;
        $randmsg = "うまく描けなかったよ……";
        $retflag = 1;
    }
}

$return_message_text = $randmsg;

if ($image_frag == 1)
{
    sending_image($accessToken, $replyToken, $return_message_text);
} elseif($retflag == 1)  {
    sending_messages($accessToken, $replyToken, $message_type, $return_message_text);
}

//メッセージの送信
function sending_messages($accessToken, $replyToken, $message_type, $return_message_text){
    //レスポンスフォーマット
    $response_format_text = [
        "type" => $message_type,
        "text" => $return_message_text
    ];

    //ポストデータ
    $post_data = [
        "replyToken" => $replyToken,
        "messages" => [$response_format_text]
    ];

    //curl実行
    $ch = curl_init("https://api.line.me/v2/bot/message/reply");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charser=UTF-8',
        'Authorization: Bearer ' . $accessToken
    ));
    $result = curl_exec($ch);
    curl_close($ch);
}

function sending_image($accessToken, $replyToken, $imageurl)
{
    $message = array('type'               => 'image',
                     'originalContentUrl' => $imageurl,
                     'previewImageUrl'    => $imageurl);
    $headers = array('Content-Type: application/json; charser=UTF-8',
                     'Authorization: Bearer ' . $accessToken);

    $body = json_encode(array('replyToken' => $replyToken,
                              'messages'   => array($message)));
    $options = array(CURLOPT_URL            => 'https://api.line.me/v2/bot/message/reply',
                     CURLOPT_CUSTOMREQUEST  => 'POST',
                     CURLOPT_RETURNTRANSFER => true,
                     CURLOPT_HTTPHEADER     => $headers,
                     CURLOPT_POSTFIELDS     => $body);

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    curl_exec($curl);
    curl_close($curl);
}

function time_diff($time_from, $time_to) 
{
    // 日時差を秒数で取得
    $dif = $time_to - $time_from;
    // 時間単位の差
    $dif_time = date("H:i:s", $dif);
    // 日付単位の差
    $dif_days = (strtotime(date("Y-m-d", $dif)) - strtotime("1970-01-01")) / 86400;
    return "{$dif_days}日と{$dif_time}";
}

function chatgpt($query)
{
    error_log("ChatGPT呼び出し\n", 3, "./my-errors.log");
    $open_ai = new OpenAi('');// <- define the variable.

    $chat = $open_ai->chat([
     'model' => 'gpt-3.5-turbo',
     'messages' => [
         [
             "role" => "user",
             "content" => "敬語は使わず、砕けた口調で返答してください。あなたの名前は“ことら”とします。"
         ],
         [
             "role" => "assistant",
             "content" => "はい！砕けた口調で返答するね！"
         ],
         [
             "role" => "user",
             "content" => $query,
         ],
     ],
     'temperature' => 0.7,
     'max_tokens' => 1000,
     'frequency_penalty' => 0,
     'presence_penalty' => 0,
    ]);
    $garray = json_decode( $chat ) ;
    $chatgptanswer=$garray->choices[0]->message->content;
    //$chatgptanswer = str_replace(array("\r\n", "\r", "\n"), "", $chatgptanswer);
    error_log("ChatGPT終了\n", 3, "./my-errors.log");
    return $chatgptanswer;
}

function dall_e($query)
{
    error_log("DALL・E呼び出し\n", 3, "./my-errors.log");
    error_log("プロンプト:" . $query . "\n", 3, "./my-errors.log");
    $open_ai = new OpenAi('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');// <- define the variable.
    $response =  $open_ai->image([
        'prompt' => $query,
        'n' => 1,
        'size' => '256x256',
        'response_format' => 'url',
    ]);
    error_log("DALL・E終了\n", 3, "./my-errors.log");
    $e = json_decode($response);
    $res = $e->data[0]->url;
    error_log($res . "\n", 3, "./my-errors.log");
    return $res;
}
