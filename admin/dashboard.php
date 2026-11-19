<?php
require '../config/db.php';
require_role('admin');
header('Location: /EL3/admin/orders.php');
exit;