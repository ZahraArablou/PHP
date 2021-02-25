<?php
require_once 'vendor/autoload.php';
require_once 'init.php';
//********************payment Message ***************/
$app->get('/paymentprocess/{bookid:[0-9]+}', function ($request, $response, $args) use ($log) {

    DB::update('booking', ['bookingStatus' => 'booked', 'isPaid' => 'yes'], "id=%i", $args['bookid']);
    setFlashMessage("Your car is booked.Payment is done!");
    $log->debug(sprintf(" booking record with Id=%s updated from IP=%s", $args['bookid'], $_SERVER['REMOTE_ADDR']));
    return $this->view->render($response, 'paymentprocess.html.twig');
});
