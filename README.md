# YMQ_service
Лабораторная работа №2 «Разработка распределенного приложения, использующего технологию передачи сообщений и сокеты» по дисциплине «Технологии разработки распределенных приложений»
server_receiver.php - ставиться на сервер(хостинг) и после запуска скрипт ожидает получения информации в течении 10 секунд, если информация принята, то будет заполнена соответсвующая таблица(таблицы) в 3 нормальной форме
db_sendler.php - запускается на клиенте(локальном) и после запуска производится отправка полей из ненормализованной бд в сервис очередей яндекса
config.env.php - конфигурационный файл с переменными
