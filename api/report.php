<?php
//report.php
require 'vendor/autoload.php';
use Twilio\Rest\Client;

$orders = json_decode(file_get_contents('data/orders.json'), true);
$totalSales = array_sum(array_column($orders,'total'));

$message = "Daily Sales Report:\nOrders: " . count($orders) . "\nTotal: ₹{$totalSales}";

$twilioSid   = $_ENV['TWILIO_SID'];
$twilioToken = $_ENV['TWILIO_TOKEN'];
$twilioFrom  = $_ENV['TWILIO_FROM'];
$ownerNumber = $_ENV['OWNER_NUMBER'];

$client = new Client($twilioSid, $twilioToken);
$client->messages->create($ownerNumber, [
  'from' => $twilioFrom,
  'body' => $message
]);
