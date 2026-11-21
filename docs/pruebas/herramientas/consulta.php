<?php
$p = new PDO('sqlite:' . $argv[1]);
echo json_encode($p->query($argv[2])->fetchAll(PDO::FETCH_NUM));
