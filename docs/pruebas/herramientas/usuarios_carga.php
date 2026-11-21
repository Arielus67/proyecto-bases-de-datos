<?php
$db = new PDO('sqlite:' . $argv[1]);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$hash = password_hash('secreto123', PASSWORD_BCRYPT, ['cost' => 4]);
$roles = $db->query("select nombre_rol, id_rol from ROL")->fetchAll(PDO::FETCH_KEY_PAIR);
$tipos = ['Mesero', 'Mesero', 'Cocinero', 'Cajero', 'Administrador'];
$db->beginTransaction();
for ($i = 1; $i <= 120; $i++) {
    $tipo = $tipos[$i % 5];
    $correo = sprintf('carga%03d@prueba.test', $i);
    $db->prepare("INSERT INTO EMPLEADO (nombre, apellido, correo, contrasena_hash, id_rol, fecha_ingreso, activo) VALUES (?, 'Carga', ?, ?, ?, datetime('now'), 1)")->execute([$tipo, $correo, $hash, $roles[$tipo]]);
    $id = $db->lastInsertId();
    if ($tipo === 'Mesero') $db->exec("INSERT INTO MESERO VALUES ($id, 'Salón')");
    if ($tipo === 'Cocinero') $db->exec("INSERT INTO COCINERO VALUES ($id, 1, 'General')");
    if ($tipo === 'Cajero') $db->exec("INSERT INTO CAJERO VALUES ($id, 'C$id')");
}
$db->commit();
echo "usuarios de carga: 120\n";
foreach (['PEDIDO','DETALLE_PEDIDO','FACTURA','PRODUCTO','MESA','EMPLEADO','PERMISO','ROL_PERMISO'] as $t) echo $t, '=', $db->query("select count(*) from $t")->fetchColumn(), ' ';
