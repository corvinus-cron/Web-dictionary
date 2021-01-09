<?php
require_once "../database/Singleton.php";
require_once "../database/Database.php";
use classes\database\Database;

$input = json_decode(file_get_contents('php://input'),true);

if (empty($input) || !isset($input['method'])) {
    send_resp(['status'=>false,'result'=>'empty method']);
} elseif($input['method']=='reinit') {
    reinitDb();
    send_resp(['status'=>true,'result'=>'success']);
} elseif($input['method']=='table') {
    $param = [];
    if (isset($input['word'])) {
        $param['filter'] = ['%word'=>$input['word']];
    }
    $words = getWords($param ?? []);
    if (empty($words)) {
        send_resp(['status'=>false,'result'=>'Нет слов']);
    }
    $table = getTable($words);
    send_resp(['status'=>true,'result'=>$table]);
} elseif($input['method']=='check_word') {
    $wordDescription = getWord($input['word']);
    if ($wordDescription!==false) {
        send_resp(['status'=>true,'result'=>$wordDescription]);
    } else {
        send_resp(['status'=>false,'result'=>'']);
    }
} elseif ($input['method']=='set_word'){
    if (empty($input['word']) || empty($input['description'])){
        send_resp(['status'=>false,'result'=>'empty inputs']);
    }
    $data = [
        'word'=>$input['word'],
        'description'=>$input['description'],
        'date_create'=>date('Y-m-d H:i:s')
    ];
    setNewWord($data);
    send_resp(['status'=>true,'result'=>'success']);
} else {
    send_resp(['status'=>false,'result'=>404]);
}

// functions
function getWord($text)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://dictionaryserver:8080/$text");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $head = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode==200) {
        return $head;
    } else {
        return false;
    }
}
function getWords($param)
{
    $db = Database::getInstance();
    $table = 'dictionary';
    $sql = $db->selectValue(
        (empty($param['select']) ? [] : $param['select']),
        empty($param['filter']) ? [] : $param['filter'],
        $db->getMapAs($table),
        empty($param['additional']) ? [] : $param['additional']
    );
    $q = "SELECT " . $sql['select'] . " FROM " . $table . $sql['where'];
    return $db->query($q);
}
function getTable($data) {
    if (empty($data)) return '';
    $table = '<div class="table table-striped">
    <h2>Словарь:</h2>
    <table border="1"  class="table">
        <thead class="thead-light">
        <tr>
            <th scope="col">#</th>
            <th scope="col"> Слово </th>
            <th scope="col"> Описание </th>
            
        </tr>
        </thead><tbody>
    ';//<th scope="col"> Дата обновления </th>
    foreach ($data as $key=>$oneElement) {
        $key++;
        $oneElement['date_create'] = date('d.m.Y H:i:s',strtotime($oneElement['date_create']));
        $table.="
        <tr>
        <td>{$key}</td>
        <td>{$oneElement['word']}</td>
        <td>{$oneElement['description']}</td>
        
        </tr>
        ";//<td>{$oneElement['date_create']}</td>
    }
    $table.=' </tbody></table></div>';
    return $table;
}
function setNewWord($data)
{
    $db = Database::getInstance();
    $table = 'dictionary';
    $word = $data['word'];
    $map = $db->getMapDef($table);
    $exist = getWords(['filter'=>['word'=>$word]]);
    if (!empty($exist)) {
        unset($data['word']);
        $q2 = 'update ' . $table . ' SET ' . $db->updateQueryData($map, $data) .
            ' where word=' . $db->escape($word);
        $db->query($q2);
    } else {
        $q1 = 'insert into ' . $table . ' (' . implode(',', array_keys($map)) . ') values ';
        $q1 .= $db->setQueryData($map, $data);
        $db->query($q1);
    }
}
function send_resp($data)
{
    header('Content-Type: application/json');
    echo json_encode($data,271);
    exit;
}
function reinitDb()
{
    Database::getInstance()->reinitDb();
}
function transliterate($string)
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
        'ь' => '\'',
        'ы' => 'y',
        'ъ' => '\'',
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
        'Ь' => '\'',
        'Ы' => 'Y',
        'Ъ' => '\'',
        'Э' => 'E',
        'Ю' => 'Yu',
        'Я' => 'Ya',
    ];
    return strtr($string, $converter);
}
