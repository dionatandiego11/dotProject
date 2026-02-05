<?php
require 'bootstrap.php';
$repo = new DotProject\Repository\UserRepository();
$users = $repo->findActive();
foreach ($users as $u) {
    echo $u->getId() . '|' . $u->getUsername() . '|' . $u->getFirstName() . '|' . $u->getLastName() . "\n";
}
