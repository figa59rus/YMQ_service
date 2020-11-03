<?php
require_once "config.env.php";
require 'vendor/autoload.php';
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use phpseclib\Crypt\RSA;

$ymq = new Aws\Sqs\SqsClient([
    'version' => SQS_VERSION,
    'region' => SQS_REGION,
    'credentials' => array('key'=>AWS_KEY,'secret'=>AWS_SECRET),
    'endpoint' => SQS_ENDPOINT
]);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $link = new mysqli(DB_HOST_LOCAL, DB_USER_LOCAL, DB_PASSWORD_LOCAL, DB_NAME_UNNORMALIZED, 3306);
    $link->set_charset('utf8mb4');
} catch (\mysqli_sql_exception $e) {
    throw new \mysqli_sql_exception($e->getMessage(), $e->getCode());
}
if ($link) // если успешно, то работаем дальше
{
    $query = "SELECT * FROM univers"; //сформировали запрос для БД
    $result = mysqli_query($link, $query); //выполнение запроса
    while ($row = mysqli_fetch_assoc($result)) { //при каждом новом результате запроса выполняем дальнейшие действия
        $message = json_encode($row);   //переводим данные в json для удобной отправки
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC"); //шифруем в режиме cipher block chaining
        $iv = openssl_random_pseudo_bytes($ivlen); // генерируем рандомную последовательность
        $ciphertext_raw = openssl_encrypt($message, $cipher, ENCRYPTION_KEY, $options = OPENSSL_RAW_DATA, $iv); //шифруем сообщение
        $hmac = hash_hmac('sha256', $ciphertext_raw, ENCRYPTION_KEY, $as_binary=true);
        $ciphertext = base64_encode($iv.$hmac.$ciphertext_raw); // кодируем в base64
        echo "\e[30;42mMESSAGE:\e[0m  ".$message."\n";
        echo "\e[30;42mENCODING MESSAGE:\e[0m  ".$ciphertext."\n\n";
        $arr = array();
        array_push($arr, $ciphertext);

        echo "\e[30;42mENCRYPTION KEY:\e[0m  ".ENCRYPTION_KEY."\n";
        array_push($arr, base64_encode(ENCRYPTION_KEY));
        $msg = json_encode($arr); // добавляем всё в массив
        $result = $ymq->sendMessage([
            'QueueUrl' => YANDEX_MQ_URL,
            'MessageBody' => $msg,
        ]);
        echo "\e[30;42mSENT MESSAGE:\e[0m  ".$msg;
    }
    echo "\e[30;42m[<---]\e[0m Sent new data for normalized database\n";
} else {
    echo "\nOops, there is an error while trying to connect to DB\n";
}
mysqli_close($link);
