<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use tools\profiling\Tools;

/**
 * Class UploaderCore.
 */
class UploaderCore
{
    public const DEFAULT_MAX_SIZE = 10485760;

    /** @var bool|null */
    private $_check_file_size;
    /** @var array<string> */
    private $_accept_types = [];
    /** @var array */
    private $_files = [];
    /** @var int */
    private $_max_size;
    /** @var string|null */
    private $_name;
    /** @var string|null */
    private $_save_path;

    /**
     * UploaderCore constructor.
     *
     * @param string|null $name
     */
    public function __construct($name = null)
    {
        $this->setName($name);
        $this->setCheckFileSize(true);
    }

    /**
     * @param array<string> $value
     *
     * @return self
     */
    public function setAcceptTypes($value)
    {
        $this->_accept_types = $value;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getAcceptTypes()
    {
        return $this->_accept_types;
    }

    /**
     * @param bool $value
     *
     * @return self
     */
    public function setCheckFileSize($value)
    {
        $this->_check_file_size = $value;

        return $this;
    }

    /**
     * @param string|null $fileName
     *
     * @return string
     */
    public function getFilePath($fileName = null)
    {
        if (!isset($fileName)) {
            return tempnam($this->getSavePath(), $this->getUniqueFileName());
        }

        $pathInfo = pathinfo($fileName);
        if (isset($pathInfo['extension'])) {
            $fileName = $pathInfo['filename'] . '.' . Tools::strtolower($pathInfo['extension']);
        }

        return $this->getSavePath() . $fileName;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->_files;
    }

    /**
     * @param int $value
     *
     * @return self
     */
    public function setMaxSize($value)
    {
        $this->_max_size = (int) $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxSize()
    {
        if (empty($this->_max_size)) {
            $this->setMaxSize(self::DEFAULT_MAX_SIZE);
        }

        return $this->_max_size;
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public function setName($value)
    {
        $this->_name = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public function setSavePath($value)
    {
        $this->_save_path = $value;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPostMaxSizeBytes()
    {
        $postMaxSize = ini_get('post_max_size');
        $bytes = (int) trim($postMaxSize);
        $last = strtolower($postMaxSize[strlen($postMaxSize) - 1]);

        switch ($last) {
            case 'g':
                $bytes *= 1024;
                // no break
            case 'm':
                $bytes *= 1024;
                // no break
            case 'k':
                $bytes *= 1024;
        }

        if ($bytes == '') {
            $bytes = null;
        }

        return $bytes;
    }

    /**
     * @return string
     */
    public function getSavePath()
    {
        if (!isset($this->_save_path)) {
            $this->setSavePath(_PS_UPLOAD_DIR_);
        }

        return $this->normalizeDirectory($this->_save_path);
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    public function getUniqueFileName($prefix = 'PS')
    {
        return uniqid($prefix, true);
    }

    /**
     * @return bool
     */
    public function checkFileSize()
    {
        return isset($this->_check_file_size) && $this->_check_file_size;
    }

    /**
     * @param null $dest
     *
     * @return array
     */
    public function process($dest = null)
    {
        $upload = isset($_FILES[$this->getName()]) ? $_FILES[$this->getName()] : null;

        if ($upload && is_array($upload['tmp_name'])) {
            $tmp = [];
            foreach ($upload['tmp_name'] as $index => $value) {
                $tmp[$index] = [
                    'tmp_name' => $upload['tmp_name'][$index],
                    'name' => $upload['name'][$index],
                    'size' => $upload['size'][$index],
                    'type' => $upload['type'][$index],
                    'error' => $upload['error'][$index],
                ];

                $this->_files[] = $this->upload($tmp[$index], $dest);
            }
        } elseif ($upload) {
            $this->_files[] = $this->upload($upload, $dest);
        }

        return $this->_files;
    }

    /**
     * @param array<string, string> $file
     * @param string|null $dest
     *
     * @return mixed
     */
    public function upload($file, $dest = null)
    {
        if ($this->validate($file)) {
            if (isset($dest) && is_dir($dest)) {
                $filePath = $dest;
            } else {
                $filePath = $this->getFilePath(isset($dest) ? $dest : $file['name']);
            }

            if ($file['tmp_name'] && is_uploaded_file($file['tmp_name'])) {
                move_uploaded_file($file['tmp_name'], $filePath);
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents($filePath, fopen('php://input', 'rb'));
            }

            $fileSize = $this->getFileSize($filePath, true);

            if ($fileSize === $file['size']) {
                $file['save_path'] = $filePath;
            } else {
                $file['size'] = $fileSize;
                unlink($filePath);
                $file['error'] = Context::getContext()->getTranslator()->trans('Server file size is different from local file size', [], 'Admin.Notifications.Error');
            }
        }

        return $file;
    }

    /**
     * @param int $error_code
     *
     * @return string|int
     */
    protected function checkUploadError($error_code)
    {
        $error = 0;
        switch ($error_code) {
            case 1:
                $error = Context::getContext()->getTranslator()->trans('The uploaded file exceeds %s', [ini_get('upload_max_filesize')], 'Admin.Notifications.Error');

                break;
            case 2:
                $error = Context::getContext()->getTranslator()->trans('The uploaded file exceeds %s', [ini_get('post_max_size')], 'Admin.Notifications.Error');

                break;
            case 3:
                $error = Context::getContext()->getTranslator()->trans('The uploaded file was only partially uploaded', [], 'Admin.Notifications.Error');

                break;
            case 4:
                $error = Context::getContext()->getTranslator()->trans('No file was uploaded', [], 'Admin.Notifications.Error');

                break;
            case 6:
                $error = Context::getContext()->getTranslator()->trans('Missing temporary folder', [], 'Admin.Notifications.Error');

                break;
            case 7:
                $error = Context::getContext()->getTranslator()->trans('Failed to write file to disk', [], 'Admin.Notifications.Error');

                break;
            case 8:
                $error = Context::getContext()->getTranslator()->trans('A PHP extension stopped the file upload', [], 'Admin.Notifications.Error');

                break;
            default:
                break;
        }

        return $error;
    }

    /**
     * @param array $file
     *
     * @return bool
     */
    protected function validate(&$file)
    {
        $file['error'] = $this->checkUploadError($file['error']);

        $postMaxSize = $this->getPostMaxSizeBytes();

        if ($postMaxSize && ($this->getServerVars('CONTENT_LENGTH') > $postMaxSize)) {
            $file['error'] = Context::getContext()->getTranslator()->trans('The uploaded file exceeds the post_max_size directive in php.ini', [], 'Admin.Notifications.Error');

            return false;
        }

        if (preg_match('/\%00/', $file['name'])) {
            $file['error'] = Context::getContext()->getTranslator()->trans('Invalid file name', [], 'Admin.Notifications.Error');

            return false;
        }

        $types = $this->getAcceptTypes();

        // TODO check mime type.
        if (!empty($types) && !in_array(Tools::strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $types)) {
            $file['error'] = Context::getContext()->getTranslator()->trans('Filetype not allowed', [], 'Admin.Notifications.Error');

            return false;
        }

        if ($this->checkFileSize() && $file['size'] > $this->getMaxSize()) {
            $file['error'] = Context::getContext()->getTranslator()->trans('File is too big. Current size is %1s, maximum size is %2s.', [$file['size'], $this->getMaxSize()], 'Admin.Notifications.Error');

            return false;
        }

        return true;
    }

    /**
     * @param string $filePath
     * @param bool $clearStatCache
     *
     * @return int
     *
     * @since 1.7.0
     */
    protected function getFileSize($filePath, $clearStatCache = false)
    {
        if ($clearStatCache) {
            clearstatcache(true, $filePath);
        }

        return filesize($filePath);
    }

    /**
     * @param string $var
     *
     * @return string
     *
     * @since 1.7.0
     */
    protected function getServerVars($var)
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : '';
    }

    /**
     * @param string $directory
     *
     * @return string
     *
     * @since 1.7.0
     */
    protected function normalizeDirectory($directory)
    {
        $last = $directory[strlen($directory) - 1];

        if (in_array($last, ['/', '\\'])) {
            $directory[strlen($directory) - 1] = DIRECTORY_SEPARATOR;

            return $directory;
        }

        $directory .= DIRECTORY_SEPARATOR;

        return $directory;
    }
}
