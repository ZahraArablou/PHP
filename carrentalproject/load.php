<?php

//load.php

$connect = new PDO('mysql:host=localhost;dbname=cp4996_carrental', 'cp4996_carrental', 'WhuaZH1umzsvstZ5');

$data = array();

$query = "SELECT * FROM booking AS b JOIN cars AS c WHERE b.carId = c.id";

$statement = $connect->prepare($query);

$statement->execute();

$result = $statement->fetchAll();

foreach ($result as $row) {
    $data[] = array(
        'id'   => $row["id"],
        'title'   => $row["make"],
        'start'   => $row["pickupDate"],
        'end'   => $row["returnDate"]
    );
}

echo json_encode($data);
