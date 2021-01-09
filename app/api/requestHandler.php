<?
namespace api;
require_once $_SERVER['DOCUMENT_ROOT']."/database/Singleton.php";
require_once $_SERVER['DOCUMENT_ROOT']."/database/Database.php";
use classes\database\Database;

class requestHandler {
    private $request;
    public function __construct($req)
    {
        $this->request = $req;
    }

    public function setReq($req)
    {
        $this->request = $req;
    }

    public function __call($name, $arguments)
    {
        return [true,'Method not found'];
    }
    private function __getWords($param)
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
    private function __getTable($data) {
        if (empty($data)) return '';
        $table = '<div class="table table-striped">
        <h2>Веб-словарь:</h2>
        <table border="1"  class="table">
            <thead class="thead-light">
            <tr>
                <th scope="col">#</th>
                <th scope="col">Термин</th>
                <th scope="col">Определение</th>
            </tr>
            </thead><tbody>
        ';
        foreach ($data as $key=>$oneElement) {
            $key++;
            $oneElement['date_create'] = date('d.m.Y H:i:s',strtotime($oneElement['date_create']));
            $table.="
            <tr>
            <td>{$key}</td>
            <td>{$oneElement['word']}</td>
            <td>{$oneElement['description']}</td>
            </tr>
            ";
        }
        $table.=' </tbody></table></div>';
        return $table;
    }
    private function __checkword($params)
    {
        $indbword = $this->__getWords($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://dictionaryserver:8080/{$params['word']}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $head = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode==200) {
            return $head;
        }
        $result = '';
        if (!empty($indbword)){
            $result .= $indbword[0]['description'];
        }
        if (!empty($head) && $httpCode==200) {
            $result .= (empty($result)?:'|').$head;
        }
        return empty($result)?false:$result;
    }
    private function __setnewword($data)
    {
        $db = Database::getInstance();
        $table = 'dictionary';
        $word = $data['word'];
        $map = $db->getMapDef($table);
        $exist = $this->__getWords(['filter' => ['word' => $word]]);
        if (!empty($exist)) {
            unset($data['word']);
            $q2 = 'update ' . $table . ' SET ' . $db->updateQueryData($map,
                    $data) . ' where word=' . $db->escape($word);
            $db->query($q2);
        } else {
            $q1 = 'insert into ' . $table . ' (' . implode(',', array_keys($map)) . ') values ';
            $q1 .= $db->setQueryData($map, $data);
            $db->query($q1);
        }
    }

    /////////////публичные обработчики методов////////////////////
    public function getWord()
    {
        $data = $this->request;
        return([true,$data]);
    }
    public function getTable()
    {

        $data = $this->request;
        if (isset($data['word'])) {
            $param['filter'] = ['%word'=>$data['word']];
        }
        $words = $this->__getWords($param ?? []);

        if (empty($words)) {
            return(['status'=>false,'result'=>'Не найдены слова']);
        }
        $table = $this->__getTable($words);
        return ['status'=>true,'result'=>$table];
    }
    public function checkword()
    {
        $data = $this->request;
        if (isset($data['word'])) {
            $param['word'] = $data['word'];
            $param['filter'] = ['%word'=>$data['word']];
        } else {
            return [false,'no args'];
        }
        $res = $this->__checkword($param);
        if ($res!==false) {
            return ['status'=>true,'result'=>$res];
        }
        return['status'=>false,'result'=>''];
    }
    public function setword()
    {
        $data = $this->request;
        if (empty($data['word']) || empty($data['description'])){
            return ['status'=>false,'result'=>'empty inputs'];
        }
        $params = [
            'word'=>$data['word'],
            'description'=>$data['description'],
            'date_create'=>date('Y-m-d H:i:s')
        ];
        
        return ['status'=>true,'result'=>$this->__setnewword($params)];
    }
}
