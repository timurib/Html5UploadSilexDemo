<?php
namespace Timurib\Html5Upload\Handler;

use Timurib\Html5Upload\Exception\IOException;

/**
 * @author Ibragimov Timur <timok@ya.ru>
 */
class UploadHandler
{

    /**
     * @var int
     */
    private $diskSpaceLimit = 104857600;

    /**
     * @var int
     */
    private $inputStreamLimit = 1048576;

    /**
     * @var string
     */
    private $tempPath;

    /**
     * @var string
     */
    private $directoryPath;

    /**
     * @var string
     */
    private $webPath;

    /**
     * @param string $tempPath
     * @param string $directoryPath
     * @param string $webPath
     */
    public function __construct($tempPath, $directoryPath, $webPath)
    {
        $this->tempPath      = $tempPath;
        $this->directoryPath = $directoryPath;
        $this->webPath       = $webPath;
    }

    /**
     * @return int
     */
    public function getDiskSpaceLimit()
    {
        return $this->diskSpaceLimit;
    }

    /**
     * @param int $diskSpaceLimit
     * @return UploadHandler
     */
    public function setDiskSpaceLimit($diskSpaceLimit)
    {
        $this->diskSpaceLimit = $diskSpaceLimit;
        return $this;
    }

    /**
     * @return int
     */
    public function getInputStreamLimit()
    {
        return $this->inputStreamLimit;
    }

    /**
     * @param int $inputStreamLimit
     * @return UploadHandler
     */
    public function setInputStreamLimit($inputStreamLimit)
    {
        $this->inputStreamLimit = $inputStreamLimit;
        return $this;
    }

    /**
     * @return string
     */
    public function generateUploadId()
    {
        return md5(microtime() . uniqid() . rand());
    }

    /**
     * @param type $uploadId
     * @param type $originalFilename
     * @return type
     */
    private function generateFilename($uploadId, $originalFilename)
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename  = md5($uploadId);
        return $basename . (strlen($extension) > 0 ? '.' . $extension : '');
    }

    /**
     * @param string $originalFilename имя загружаемого файла
     * @param int $fileSize размер в байтах
     * @return string уникальный идентификатор процесса загрузки
     * @throws \InvalidArgumentException
     * @throws NotEnoughSpaceException
     * @throws IOException
     */
    public function uploadStart($originalFilename, $fileSize)
    {
        if ($fileSize <= 0) {
            throw new \InvalidArgumentException('File size must be more than 0');
        }
        $residualSpace = $this->diskSpaceLimit - $this->diskSpaceRealUsage() - $fileSize;
        if ($residualSpace < 0) {
            throw new NotEnoughSpaceException(-$residualSpace);
        }
        if (!is_writable($this->tempPath)) {
            throw new IOException(sprintf(
                'Unable to write in directory %s', $this->tempPath
            ));
        }
        $uploadId = $this->generateUploadId();
        $tempname = $this->tempPath . '/' . $this->generateFilename($uploadId, $originalFilename);
        $tempfile = fopen($tempname, 'w+');
        if ($tempfile === false) {
            throw new IOException(sprintf(
                'Unable to create temporary file %s', $tempname
            ));
        }
        if (fclose($tempfile) === false) {
            throw new IOException(sprintf(
                'Unable to close created temporary file %s', $tempname
            ));
        }
        return $uploadId;
    }

    /**
     * @param string $uploadId
     * @param string $originalFilename
     *
     * @return int
     *
     * @throws InvalidUploadException
     * @throws IOException
     * @throws ChunkExceededException
     * @throws NotEnoughSpaceException
     */
    public function uploadChunk($uploadId, $originalFilename)
    {
        $filename = $this->generateFilename($uploadId, $originalFilename);
        $tempname = $this->tempPath . '/' . $filename;
        if (!file_exists($tempname)) {
            throw new InvalidUploadException(sprintf(
                'Not found temporary file for %s (uploaded file: %s)', $uploadId, $originalFilename
            ));
        }
        try {
            $inputStream = fopen('php://input', 'r');
            if ($inputStream === false) {
                throw new IOException('Unable to open input stream');
            }
            if (($chunk = stream_get_contents($inputStream, $this->inputStreamLimit)) === false) {
                throw new IOException('Unable to read input stream');
            }
            if (!feof($inputStream)) {
                throw new ChunkExceededException($this->inputStreamLimit);
            }
            $residualSpace = $this->diskSpaceLimit - $this->diskSpaceRealUsage() - strlen($chunk);
            if ($residualSpace < 0) {
                throw new NotEnoughSpaceException(-$residualSpace);
            }
            $numBytes = file_put_contents($tempname, $chunk, FILE_APPEND | LOCK_EX);
            if ($numBytes === false) {
                throw new IOException(sprintf(
                    'Unable to write uploaded file ' . $tempname
                ));
            }
            if (!fclose($inputStream)) {
                throw new IOException('Unable to close input stream');
            }
            return $numBytes;
        } catch (\Exception $e) {
            if (@unlink($tempname) === false) {
                throw new IOException(sprintf(
                    'Unable to delete uploaded file %s', $tempname
                ), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * @param string $uploadId
     * @param string $originalFilename
     * @return string
     * @throws IOException
     * @throws InvalidUploadException
     */
    public function uploadComplete($uploadId, $originalFilename)
    {
        if (!is_writable($this->directoryPath)) {
            throw new IOException(sprintf(
                'Unable to write in directory %s', $this->directoryPath
            ));
        }
        $filename = $this->generateFilename($uploadId, $originalFilename);
        $tempname = $this->tempPath . '/' . $filename;
        if (!file_exists($tempname)) {
            throw new InvalidUploadException(sprintf(
                'Not found temporary file for %s (uploaded file: %s)', $uploadId, $originalFilename
            ));
        }
        $pathname = $this->directoryPath . '/' . $filename;
        try {
            if (!rename($tempname, $pathname)) {
                throw new IOException(sprintf(
                    'Unable to move file from %s to %s', $tempname, $pathname
                ));
            }
            if (($size = filesize($pathname)) === false) {
                throw new IOException(sprintf(
                    'Unable to get size of %s', $pathname
                ));
            }
            return $this->webPath.'/'.$this->generateFilename($uploadId, $originalFilename);
        } catch (\Exception $e) {
            if (@unlink($pathname) === false) {
                throw new IOException(sprintf(
                    'Unable to delete uploaded file %s', $pathname
                ), 0, $e);
            }
            throw $e;
        }
    }

    /**
     * @return int
     */
    public function diskSpaceRealUsage()
    {
        $totalSize = 0;
        foreach ($this->scanTemp() as $file) {
            $totalSize += $file->getSize();
        }
        foreach ($this->scanDirectory() as $file) {
            $totalSize += $file->getSize();
        }
        return $totalSize;
    }

    /**
     * @return array
     * @throws IOException
     */
    public function scanDirectory()
    {
        return $this->scanFiles($this->directoryPath);
    }

    /**
     * @return array
     * @throws IOException
     */
    public function scanTemp()
    {
        return $this->scanFiles($this->tempPath);
    }

    /**
     * @param string $dir
     * @return \FilesystemIterator
     * @throws IOException
     */
    private function scanFiles($dir)
    {
        if (!is_readable($dir)) {
            throw new IOException(sprintf('Unable to read temporary files directory %s', $dir));
        }
        $iterator = new \FilesystemIterator($dir);
        $files    = array();
        foreach ($iterator as $item) {
            /* @var \SplFileInfo $item */
            if ($item->isFile() && substr($item->getBasename(), 0, 1) !== '.') {
                $files[] = $item;
            }
        }
        return $files;
    }

    /**
     * @throws IOException
     */
    public function clearTempDir()
    {
        if (!is_writable($this->tempPath)) {
            throw new IOException(sprintf('Unable to clear temporary files directory %s', $this->tempPath));
        }
        $files = $this->scanTemp();
        foreach ($files as $file) {
            /* @var \SplFileInfo $file */
            if (@unlink($file->getPathname()) === false) {
                throw new IOException(sprintf('Unable to delete file %s', $file->getPathname()));
            }
        }
    }

}
