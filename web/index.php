<?php
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Zend\Crypt\BlockCipher;
use Zend\Crypt\Symmetric\Mcrypt;


// timezone
date_default_timezone_set('America/Sao_Paulo');

// web/index.php
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
/* Connect to mysql database */
$dsn = 'mysql:dbname=teste;host=localhost;charset=utf8';
try {
    $dbh = new PDO($dsn, 'livros', 'livros2016');
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

/* Rotas */
$app->get('/', function () use ($app, $dbh) {

    $blockCipher = BlockCipher::factory('mcrypt', array('algo' => 'aes'));
    $blockCipher->setKey('encryption key');
    $result = $blockCipher->encrypt('this is a secret message');
    echo "Encrypted text: $result \n";
    die;
});


$app->get('/livros', function () use ($app, $dbh) {
    // consulta todos livros
    $sth = $dbh->prepare('SELECT id, titulo, autor, isbn FROM livros');
    $sth->execute();
    $livros = $sth->fetchAll(PDO::FETCH_ASSOC);

    return $app->json($livros);
});

$app->get('/livros/{id}', function ($id) use ($app, $dbh) {
    $sth = $dbh->prepare('SELECT id, titulo, autor, isbn FROM livros WHERE id=?');
    $sth->execute([ $id ]);

    $livro = $sth->fetchAll(PDO::FETCH_ASSOC);
    if(empty($livro)) {
        // nao encontrado, 404
        return new Response("Livro com id {$id} não encontrado para consulta!", 404);
    }

    return $app->json($livro);
})->assert('id', '\d+');


// POST - incluir
$app->post('/livros', function(Request $request) use ($app, $dbh) {
    $dados = json_decode($request->getContent(), true);

    $sth = $dbh->prepare('INSERT INTO livros (titulo, autor, isbn)
            VALUES(:titulo, :autor, :isbn)');

    $sth->execute($dados);
    $id = $dbh->lastInsertId();

    // response, 201 created
    $response = new Response('Ok', 201);
    $response->headers->set('Location', "/livros/$id");
    return $response;
});


// PUT - editar (toda estrutura)
$app->put('/livros/{id}', function(Request $request, $id) use ($app, $dbh) {
    $dados = json_decode($request->getContent(), true);
    $dados['id'] = $id;

    $sth = $dbh->prepare('UPDATE livros
            SET titulo=:titulo, autor=:autor, isbn=:isbn
            WHERE id=:id');

    $sth->execute($dados);
    return $app->json($dados, 200);
})->assert('id', '\d+');

// DELETE - excluir
$app->delete('/livros/{id}', function($id) use ($app, $dbh) {
    $sth = $dbh->prepare('DELETE FROM livros WHERE id = ?');
    $sth->execute([ $id ]);

    if($sth->rowCount() < 1) {
        return new Response("Livro com id {$id} não encontrado para exclusão!", 404);
    }

    // registro foi excluido, retornar 204 - no content
    return new Response(null, 204);
})->assert('id', '\d+');

$app->run();
