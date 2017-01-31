<?php
namespace MyApp;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;

class DefaultController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', function (Application $app) {
            $stmt = $app['db']->executeQuery(
                'SELECT f.* FROM farms f LEFT JOIN farms_meta m ON m.wwoof_id = f.wwoof_id WHERE (m.refused IS NULL OR m.refused = 0) AND (m.like IS NULL OR m.like = 0) AND (m.meh IS NULL OR m.meh = 0)'
            );
            return $app['twig']->render('index.html.twig', [
                'farms' => $stmt->fetchAll(),
            ]);
        });

        $controllers->get('/likes', function (Application $app) {
            $stmt = $app['db']->executeQuery(
                'SELECT f.* FROM farms f LEFT JOIN farms_meta m ON m.wwoof_id = f.wwoof_id WHERE m.like = 1'
            );
            return $app['twig']->render('index.html.twig', [
                'farms' => $stmt->fetchAll(),
            ]);
        });

        $controllers->get('/meh', function (Application $app) {
            $stmt = $app['db']->executeQuery(
                'SELECT f.* FROM farms f LEFT JOIN farms_meta m ON m.wwoof_id = f.wwoof_id WHERE m.meh = 1'
            );
            return $app['twig']->render('index.html.twig', [
                'farms' => $stmt->fetchAll(),
            ]);
        });

        $controllers->get('/refuse/{id}', function (Application $app, $id) {
            $stmt = $app['db']->insert('farms_meta', [
                'refused' => 1,
                'meh' => 0,
                'like' => 0,
                'wwoof_id' => $id,
            ]);
            return $app->redirect('/');
        });

        $controllers->get('/like/{id}', function (Application $app, $id) {
            $stmt = $app['db']->insert('farms_meta', [
                'meh' => 0,
                'refused' => 0,
                'like' => 1,
                'wwoof_id' => $id,
            ]);
            return $app->redirect('/');
        });

        $controllers->get('/meh/{id}', function (Application $app, $id) {
            $stmt = $app['db']->insert('farms_meta', [
                'refused' => 0,
                'like' => 0,
                'meh' => 1,
                'wwoof_id' => $id,
            ]);
            return $app->redirect('/');
        });

        return $controllers;
    }
}
