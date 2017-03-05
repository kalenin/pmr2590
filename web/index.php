<?php
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

// timezone
date_default_timezone_set('America/Sao_Paulo');

// web/index.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/JWTWrapper.php';

$app = new Silex\Application();

$app['debug'] = true;

/* Connect to mysql database */
$dsn = 'mysql:dbname=teste;host=127.0.0.1;charset=utf8';
try {
    $dbh = new PDO($dsn, 'root', 'root');
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$app->get('/teste', function() use ($app) {

    $jwt = JWTWrapper::encode([
        'expiration_sec' => 3600,
        'iss' => 'pmr2590.local',
        'userdata' => [
            'id' => 1,
            'name' => 'Teste'
        ]
    ]);

    $data = JWTWrapper::decode($jwt);
    print_r($data);

    return $jwt;
});

// Autenticacao
$app->post('/auth', function (Request $request) use ($app) {
    $dados = json_decode($request->getContent(), true);

    if($dados['user'] == 'foo' && $dados['pass'] == 'bar') {
        // autenticacao valida, gerar token
        $jwt = JWTWrapper::encode([
            'expiration_sec' => 3600,
            'iss' => 'pmr2590.local',
            'userdata' => [
                'id' => 1,
                'name' => 'Teste'
            ]
        ]);

        return $app->json([
            'login' => 'true',
            'access_token' => $jwt
        ]);
    }

    return $app->json([
        'login' => 'false',
        'message' => 'Login Inválido',
    ]);
});

/* Rotas */
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
// verificar autenticacao
$app->before(function(Request $request, $app) {
    $route = $request->get('_route');

    if($route != 'POST_auth') {

        $authorization = $request->headers->get("http-authorization");
        list($jwt) = sscanf($authorization, 'Bearer %s');
        if($jwt) {
            try {
                $app['jwt'] = JWTWrapper::decode($jwt);
            } catch(Exception $ex) {
                // nao foi possivel decodificar o token jwt
                return new Response('Acesso nao autorizado', 400);
            }

        } else {
            // nao foi possivel extrair token do header Authorization
            return new Response('Token nao informado', 400);

        }
    }
});




$app->run();
