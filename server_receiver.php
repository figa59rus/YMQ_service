<?php
require_once "config.env.php";
require 'vendor/autoload.php';
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
function main()
{
    $link = mysqli_connect(DB_HOST_REMOTE, DB_USER_HOST, DB_PASSWORD_HOST, DB_NAME_NORMALIZED); // соединение с БД
    $ymq = new Aws\Sqs\SqsClient([
        'version' => SQS_VERSION,
        'region' => SQS_REGION,
        'credentials' => array('key'=>AWS_KEY,'secret'=>AWS_SECRET),
        'endpoint' => SQS_ENDPOINT
    ]);
    $result = $ymq->receiveMessage([
        'QueueUrl' => YANDEX_MQ_URL,
        'WaitTimeSeconds' => 10,
    ]);
    foreach ($result["Messages"] as $msg) {
        echo('Message received:' . PHP_EOL);
        echo('ID: ' . $msg['MessageId'] . PHP_EOL);
        echo('Body: ' . json_decode($msg['Body'])[0] . PHP_EOL . json_decode($msg['Body'])[1]);
        $mesg = json_decode($msg['Body']); // переводим тело сообщения из json в array
        $c = base64_decode($mesg[0]); // декодируем данные из БД, которые пока что зашифрованы
        $encrypt_key = base64_decode($mesg[1]);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC"); // длина шифрования в режиме AES-128-CBC
        $iv = substr($c, 0, $ivlen); // разбивание цепочки на блоки
        $hmac = substr($c, $ivlen, $sha2len = 32); // и здесь
        $ciphertext_raw = substr($c, $ivlen + $sha2len); //и здесь
        $plaintext = openssl_decrypt($ciphertext_raw, $cipher, $encrypt_key, $options = OPENSSL_RAW_DATA, $iv); //дешифровка данных из БД
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $encrypt_key, $as_binary = true);
        if (hash_equals($hmac, $calcmac)) //сравниваем хеш, если все сходится, то можно работать дальше
        {
            $message = json_decode($plaintext); //переводим из json БДшные данные
            $query = "SELECT * FROM university WHERE EXISTS 
    (SELECT univer_name FROM university WHERE univer_name='" . $message->univer_name . "')";
            $check = mysqli_query($link, $query);
            list($total) = mysqli_fetch_row($check);
            if($total==0){
                $query = "INSERT INTO university SET univer_name='" . $message->univer_name . "'"; //формируем и выполяем запросы для заполнения нормализованной БД
                mysqli_query($link, $query);
            }
            $query = "SELECT * FROM faculty WHERE EXISTS 
    (SELECT faculty.fac_name, faculty.id_univer FROM faculty WHERE faculty.fac_name='" . $message->fac_name . "' 
        AND id_univer=(SELECT univer_id FROM university WHERE university.univer_name='" . $message->univer_name. "'))";
            $check = mysqli_query($link, $query);
            list($total) = mysqli_fetch_row($check);
            if($total==0){
                $query = "INSERT INTO faculty SET faculty.fac_name='" . $message->fac_name . "', 
                id_univer=(SELECT univer_id FROM university WHERE university.univer_name='" . $message->univer_name. "')";
                mysqli_query($link, $query);
            }
            $query = "SELECT * FROM department  WHERE EXISTS 
	(SELECT department.dep_name, department.id_fac FROM department WHERE department.dep_name='" . $message->dep_name . "' 
		AND department.id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
		AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "')))";
            $check = mysqli_query($link, $query);
            list($total) = mysqli_fetch_row($check);
            if($total==0){
                $query = "INSERT INTO department SET dep_name='" . $message->dep_name . "', 
	id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
		AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "'))";
                mysqli_query($link, $query);
            }
            $query = "SELECT * FROM student WHERE EXISTS 
    (SELECT student.stud_name, student.id_dep FROM student WHERE student.stud_name='" . $message->stud_name . "' 
        AND student.id_dep=(SELECT dep_id FROM department WHERE department.dep_name='" . $message->dep_name. "' 
        AND department.id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
        AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "'))))";
            $check = mysqli_query($link, $query);
            list($total) = mysqli_fetch_row($check);
            if($total==0) {
                $query = "INSERT INTO student SET stud_name='" . $message->stud_name . "', 
	id_dep=(SELECT dep_id FROM department WHERE department.dep_name='" . $message->dep_name. "' 
        AND department.id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
        AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "')))";
                mysqli_query($link, $query);
            }
            $query = "SELECT * FROM employee WHERE EXISTS 
    (SELECT employee.empl_name, employee.id_dep FROM employee WHERE employee.empl_name='" . $message->empl_name . "' 
        AND employee.id_dep=(SELECT dep_id FROM department WHERE department.dep_name='" . $message->dep_name. "' 
        AND department.id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
        AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "'))))";
            $check = mysqli_query($link, $query);
            list($total) = mysqli_fetch_row($check);
            if($total==0) {
                $query = "INSERT INTO employee SET empl_name='" . $message->empl_name . "', 
	id_dep=(SELECT dep_id FROM department WHERE department.dep_name='" . $message->dep_name. "' 
        AND department.id_fac=(SELECT fac_id FROM faculty WHERE faculty.fac_name='" . $message->fac_name. "' 
        AND faculty.id_univer=(SELECT univer_id FROM university WHERE univer_name='" . $message->univer_name . "')))";
                mysqli_query($link, $query);
            }
            echo " [--->] Received new data for normalized database\n";
        }
//тут скрипт проверки
        $ymq->deleteMessage([
            'QueueUrl' => YANDEX_MQ_URL,
            'ReceiptHandle' => $msg['ReceiptHandle'],
        ]);
    }
    mysqli_close($link);
}
main();
