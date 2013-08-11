<?php
namespace Timurib\Html5Upload\Provider;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Timurib\Html5Upload\Handler\UploadHandler;

/**
 * @author Timur Ibragimov <timok@ya.ru>
 */
class UploadControllerProvider implements ControllerProviderInterface
{
    /**
     * @param \Silex\Application $app
     * @return \Silex\Application
     */
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', array($this, 'formAction'));
        $controllers->post('/upload/', array($this, 'uploadAction'));

        return $controllers;
    }

    /**
     * @return string
     */
    public function formAction()
    {
        ob_start();
        include __DIR__ . '/../Resources/views/example.html.php';
        return ob_get_clean();
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws BadRequestHttpException
     */
    public function uploadAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException('Illegal request type');
        }
        $headers = $request->headers;
        foreach (array('X-Upload-State', 'X-File-Name', 'X-File-Size') as $header) {
            if (!$headers->has($header)) {
                throw new BadRequestHttpException(sprintf('Missing HTTP header %s', $header));
            }
        }
        $uploadId         = $headers->get('X-Upload-Id');
        $originalFilename = $headers->get('X-File-Name');
        $fileSize         = $headers->get('X-File-Size');
        $state            = $headers->get('X-Upload-State');
        $handler          = new UploadHandler('/tmp', __DIR__.'/../../../../web/upload', '/upload');
        try {
            switch ($state) {
                case 'start':
                    $uploadId = $handler->uploadStart($originalFilename, $fileSize);
                    $data     = array(
                        'status' => 'upload_start',
                        'id'     => $uploadId,
                    );
                    break;
                case 'chunk':
                    if (!$headers->has('X-Upload-Id')) {
                        throw new BadRequestHttpException(sprintf('Missing HTTP header X-Upload-Id'));
                    }
                    $uploadId  = $headers->get('X-Upload-Id');
                    $chunkSize = $handler->uploadChunk($uploadId, $originalFilename);
                    $data      = array(
                        'status'   => 'chunk_received',
                        'received' => $chunkSize,
                    );
                    break;
                case 'complete':
                    if (!$headers->has('X-Upload-Id')) {
                        throw new BadRequestHttpException(sprintf('Missing HTTP header X-Upload-Id'));
                    }
                    $uploadId  = $headers->get('X-Upload-Id');
                    $url       = $handler->uploadComplete($uploadId, $originalFilename);
                    $data      = array(
                        'status' => 'upload_complete',
                        'url'    => $url,
                    );
                    break;
                default:
                    throw new BadRequestHttpException('Invalid value of X-Upload-State header');
            }
        } catch (ChunkExceededException $e) {
            // Клиенту отдаем ошибку 400, а исключение бросаем вверх по стеку
            throw new BadRequestHttpException(null, $e);
        } catch (InvalidUploadException $e) {
            // Клиенту отдаем ошибку 400, а исключение бросаем вверх по стеку
            throw new BadRequestHttpException(null, $e);
        } catch (NotEnoughSpaceException $e) {
            $this->get('logger')->warning($e->getMessage());
            $deficit = ceil($e->getDeficit() / 1024 / 1024);
            $data    = array(
                'status'  => 'client_error',
                'message' => sprintf('Not enough space on the disk (need another %d MB)', $deficit),
            );
        }
        return new JsonResponse($data);
    }

}
