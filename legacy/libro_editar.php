<?php
include 'includes/conexion.php';
include 'includes/funciones.php';
verificar_sesion();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $isbn = trim(isset($_POST['isbn']) ? $_POST['isbn'] : '');
    $titulo_libro = trim(isset($_POST['titulo']) ? $_POST['titulo'] : '');
    $autor = trim(isset($_POST['autor']) ? $_POST['autor'] : '');
    $anio = (isset($_POST['anio']) && $_POST['anio'] != '') ? (int)$_POST['anio'] : null;

    if ($titulo_libro == '' || $autor == '') {
        redirigir('libro_editar.php' . ($id ? '?id=' . $id : ''), 'El título y el autor son obligatorios.', 'error');
    }

    if ($id) {
        $consulta = $db->prepare('UPDATE libros SET isbn = ?, titulo = ?, autor = ?, anio = ? WHERE id = ?');
        $consulta->execute(array($isbn, $titulo_libro, $autor, $anio, $id));
        redirigir('libros.php', 'Libro actualizado.');
    } else {
        $consulta = $db->prepare('INSERT INTO libros (isbn, titulo, autor, anio) VALUES (?, ?, ?, ?)');
        $consulta->execute(array($isbn, $titulo_libro, $autor, $anio));
        $nuevo = $db->lastInsertId();
        redirigir('ejemplares.php?libro_id=' . $nuevo, 'Libro registrado. Ahora puedes agregar sus ejemplares.');
    }
}

$libro = array('id' => 0, 'isbn' => '', 'titulo' => '', 'autor' => '', 'anio' => '');
if ($id) {
    $consulta = $db->prepare('SELECT * FROM libros WHERE id = ?');
    $consulta->execute(array($id));
    $libro = $consulta->fetch();
    if (!$libro) {
        redirigir('libros.php', 'El libro no existe.', 'error');
    }
}

$titulo = $id ? 'Editar libro' : 'Nuevo libro';
include 'includes/cabecera.php';
?>
<h2><?php echo $id ? 'Editar libro' : 'Nuevo libro'; ?></h2>

<form class="formulario" method="post" action="libro_editar.php">
    <input type="hidden" name="id" value="<?php echo $libro['id']; ?>">
    <p>
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($libro['titulo']); ?>">
    </p>
    <p>
        <label for="autor">Autor</label>
        <input type="text" id="autor" name="autor" value="<?php echo htmlspecialchars($libro['autor']); ?>">
    </p>
    <p>
        <label for="anio">Año de publicación</label>
        <input type="number" id="anio" name="anio" value="<?php echo htmlspecialchars($libro['anio']); ?>">
    </p>
    <p>
        <label for="isbn">ISBN</label>
        <input type="text" id="isbn" name="isbn" value="<?php echo htmlspecialchars($libro['isbn']); ?>">
    </p>
    <p>
        <input type="submit" value="Guardar">
        <a href="libros.php">Cancelar</a>
    </p>
</form>
<?php include 'includes/pie.php'; ?>
