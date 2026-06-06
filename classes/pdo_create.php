<?php

class pdo_create
{
    public $dbs;
    public $last_error = '';

    function __construct($user, $host, $pass, $db)
    {
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $charset = 'utf8';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        try {
            $this->dbs = new PDO($dsn, $user, $pass, $opt);
            $GLOBALS['info'] = "Связь установлена";
        } catch (Exception $e) {
            $GLOBALS['info'] = "Связь не установлена";
        }
    }

    private function convertToWebP($source, $dest = null, $quality = 80)
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        if ($dest === null) {
            $info = pathinfo($source);
            $dest = $info['dirname'] . '/' . $info['filename'] . '.webp';
        }

        if (file_exists($dest) && filemtime($dest) >= filemtime($source)) {
            return $dest;
        }

        $imageInfo = getimagesize($source);
        if (!$imageInfo) {
            $this->last_error = 'Не удалось определить тип изображения';
            return false;
        }

        $mime = $imageInfo['mime'];
        $image = null;

        try {
            switch ($mime) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($source);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($source);
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($source);
                    break;
                case 'image/webp':
                    if ($dest !== $source) {
                        copy($source, $dest);
                    }
                    return $dest;
                default:
                    $this->last_error = "Неподдерживаемый тип изображения: {$mime}";
                    return false;
            }

            if (!$image) {
                $this->last_error = 'Не удалось создать изображение из файла';
                return false;
            }

            $result = imagewebp($image, $dest, $quality);
            imagedestroy($image);

            if (!$result) {
                $this->last_error = 'Ошибка конвертации в WebP';
                return false;
            }

            return $dest;
        } catch (Exception $e) {
            $this->last_error = 'Исключение при конвертации: ' . $e->getMessage();
            error_log('WebP conversion exception: ' . $e->getMessage());
            return false;
        }
    }

    public function replaceParam($param, $type)
    {
        if ($param === null) {
            $param = '';
        }

        switch ($type) {
            case "atr":
                $param = trim($param);
                break;
            case "md5":
                $param = md5(trim($param));
                break;
        }
        return $param;
    }

    public function actionTable($action, $param, $table)
    {
        $table = '`' . str_replace('`', '``', $table) . '`';

        switch ($action) {
            case 'add':
                $fields = [];
                $placeholders = [];
                $values = [];

                foreach ($param as $key => $value) {
                    $field = '`' . str_replace('`', '``', $key) . '`';
                    $fields[] = $field;
                    $placeholder = ':' . $key;
                    $placeholders[] = $placeholder;

                    $values[$placeholder] = $value;
                }

                $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

                $stmt = $this->dbs->prepare($sql);
                return $stmt->execute($values);

            case 'edit':
                $sets = [];
                $values = [];

                foreach ($param as $key => $value) {
                    if ($key === 'id')
                        continue;

                    $field = '`' . str_replace('`', '``', $key) . '`';
                    $placeholder = ':' . $key;
                    $sets[] = "$field = $placeholder";

                    if (in_array($key, ['pasw', 'pass', 'pas', 'passw', 'password'])) {
                        $values[$placeholder] = password_hash($value, PASSWORD_DEFAULT);
                    } else {
                        $values[$placeholder] = $value;
                    }
                }

                if (!isset($param['id'])) {
                    return false;
                }

                $values[':id'] = $param['id'];
                $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id";

                $stmt = $this->dbs->prepare($sql);
                return $stmt->execute($values);

            case 'del':
                if (!isset($param['id'])) {
                    return false;
                }

                $sql = "DELETE FROM $table WHERE id = :id";
                $stmt = $this->dbs->prepare($sql);
                return $stmt->execute([':id' => $param['id']]);
        }

        return false;
    }

    public function translit($str)
    {
        $converter = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ь' => '',
            'ы' => 'y',
            'ъ' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'E',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sch',
            'Ь' => '',
            'Ы' => 'Y',
            'Ъ' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
        ];
        return strtr($str, $converter);
    }

    public function uploading($input = 'files', $path = '/public/uploads', $prefix = '')
    {
        $input_name = $input;
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024;

        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $path;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!isset($_FILES[$input_name])) {
            return false;
        }

        $files = [];
        if (is_array($_FILES[$input_name]['name'])) {
            foreach ($_FILES[$input_name]['name'] as $i => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $_FILES[$input_name]['type'][$i],
                    'tmp_name' => $_FILES[$input_name]['tmp_name'][$i],
                    'error' => $_FILES[$input_name]['error'][$i],
                    'size' => $_FILES[$input_name]['size'][$i],
                ];
            }
        } else {
            $files[] = $_FILES[$input_name];
        }

        $uploaded_paths = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер, установленный в PHP',
                    UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер, установленный в форме',
                    UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
                    UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
                    UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
                    UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
                    UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла'
                ];
                $this->last_error = $errorMessages[$file['error']] ?? 'Неизвестная ошибка загрузки';
                continue;
            }

            if ($file['size'] > $max_size) {
                $this->last_error = 'Файл не должен превышать 5MB';
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions)) {
                $this->last_error = 'Разрешены только изображения (JPEG, PNG, GIF, WEBP)';
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed_mime = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed_mime)) {
                $this->last_error = 'Файл не является валидным изображением';
                continue;
            }

            $imageInfo = getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                $this->last_error = 'Файл поврежден или не является изображением';
                continue;
            }

            if (!empty($prefix)) {
                $base_name = $prefix . '_' . uniqid();
            } else {
                $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
                $safe_name = preg_replace('/[^a-zA-Zа-яА-Я0-9\s]/u', '', $original_name);
                $safe_name = $this->translit($safe_name);
                $safe_name = preg_replace('/\s+/', '_', $safe_name);
                if (empty($safe_name)) {
                    $safe_name = 'file';
                }
                $base_name = $safe_name . '_' . uniqid();
            }

            if ($ext === 'webp') {
                $final_name = $base_name . '.webp';
                $destination = $upload_dir . '/' . $final_name;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $uploaded_paths[] = $path . '/' . $final_name;
                    error_log("WEBP файл сохранён: $destination");
                } else {
                    $this->last_error = 'Ошибка при сохранении WEBP файла';
                }
                continue;
            }

            $original_name_full = $base_name . '.' . $ext;
            $original_dest = $upload_dir . '/' . $original_name_full;

            if (!move_uploaded_file($file['tmp_name'], $original_dest)) {
                $this->last_error = 'Ошибка при сохранении файла';
                continue;
            }

            $webp_dest = $upload_dir . '/' . $base_name . '.webp';

            try {
                $webp_created = $this->convertToWebP($original_dest, $webp_dest, 80);
            } catch (Exception $e) {
                error_log("WebP conversion error: " . $e->getMessage());
                $webp_created = false;
            }

            if ($webp_created && file_exists($webp_dest)) {
                $uploaded_paths[] = $path . '/' . $base_name . '.webp';

                if (file_exists($original_dest)) {
                    unlink($original_dest);
                    error_log("Оригинал удалён: $original_dest");
                }
            } else {
                $uploaded_paths[] = $path . '/' . $original_name_full;
                error_log("WebP не создан, оставлен оригинал: $original_dest");
            }
        }

        if (!empty($uploaded_paths)) {
            $this->last_error = '';
            return $uploaded_paths;
        }
        return false;
    }

    public function message($text, $type = 'info')
    {
        $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $icon = '';
        switch ($type) {
            case 'success':
                $icon = '<i class="fas fa-check-circle"></i>';
                break;
            case 'error':
                $icon = '<i class="fas fa-exclamation-triangle"></i>';
                break;
            case 'info':
            default:
                $icon = '<i class="fas fa-info-circle"></i>';
                break;
        }

        $mes = "<div class=\"toast $type show\" role=\"alert\" aria-live=\"assertive\" aria-atomic=\"true\">
        <div class=\"toast-header\">
            <button type=\"button\" class=\"btn-close\" aria-label=\"Закрыть\">&times;</button>
        </div>
        <div class=\"toast-body\">
            $icon
            <span class=\"toast-message\">$safe_text</span>
        </div>
    </div>";
        return $mes;
    }
}