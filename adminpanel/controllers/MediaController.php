<?php

namespace herbie\plugin\adminpanel\controllers;

use Herbie;

class MediaController extends Controller
{

    public function addFolderAction($query, $request)
    {
        $dir = strtolower(trim($request->get('dir')));
        $name = strtolower(trim($request->get('name')));
        $path = $this->app['alias']->get('@media/' . $dir . '/' . $name);
        if (empty($name)) {
            $this->sendErrorHeader($this->t('Name cannot be empty.'));
        }
        if (is_dir($path)) {
            $this->sendErrorHeader($this->t('A folder with the same name already exists.'));
        }
        if (!@mkdir($path)) {
            $this->sendErrorHeader($this->t('Folder {name} can not be created.', ['{name}' => $name]));
        }
        $query->add(['dir' => $dir]);
        return $this->indexAction($query, $request);
    }

    public function indexAction($query, $request)
    {
        $dir = $query->get('dir', '');
        $dir = str_replace(['../', '..', './', '.'], '', trim($dir, '/'));
        $path = $this->app['alias']->get('@media/' . $dir);
        $root = $this->app['alias']->get('@media');

        $iterator = null;
        if (is_readable($path)) {
            $directoryIterator = new Herbie\Iterator\DirectoryIterator($path, $root);
            $iterator = new Herbie\Iterator\DirectoryDotFilter($directoryIterator);
        }

        return $this->render('media/index.twig', [
            'iterator' => $iterator,
            'dir' => $dir,
            'parentDir' => str_replace('.', '', dirname($dir)),
            'root' => $root
        ]);
    }

    public function deleteAction($query, $request)
    {
        $path = $request->get('file');
        $path = str_replace(['../', '..', './'], '', trim($path, '/'));
        $absPath = $this->app['alias']->get('@media/' . $path);
        $name = basename($absPath);

        if (is_file($absPath) && !@unlink($absPath)) {
            $this->sendErrorHeader($this->t('File {file} can not be deleted.', ['{file}' => $name]));
        } elseif (is_dir($absPath) && !@rmdir($absPath)) {
            if (count(scandir($absPath)) >= 2) {
                $this->sendErrorHeader($this->t('Folder {name} is not empty and can not be deleted.', ['{name}' => $name]));
            }
            $this->sendErrorHeader($this->t('Folder {name} can not be deleted.', ['{name}' => $name]));
        }
        header('Content-Type: application/json');
        echo json_encode(true);
        exit;
    }

    public function uploadAction($query, $request)
    {
        $data = array();
        $dir = strtolower(trim($request->get('dir')));

        if (!empty($_FILES)) {
            $files = array();

            $uploaddir = $this->app['alias']->get("@media/{$dir}/");
            foreach ($_FILES as $file) {
                if (move_uploaded_file($file['tmp_name'], $uploaddir . basename($file['name']))) {
                    $files[] = $uploaddir . $file['name'];
                } else {
                    $this->sendErrorHeader($this->t('An error occured at upload.'));
                }
            }
            $data = array('files' => $files);
        } else {
            $this->sendErrorHeader($this->t('Please choose at least one file.'));
        }

        $query->add(['dir' => $dir]);
        $data['html'] = $this->indexAction($query, $request);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
