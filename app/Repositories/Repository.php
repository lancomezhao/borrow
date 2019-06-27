<?php
namespace App\Repositories;

use App\Repositories\Contract\ApiRepositoriesInterface;
use App\Repositories\Contract\RepositoryInterface;
use Illuminate\Container\Container as App;
use Illuminate\Database\Eloquent\Model;

abstract class Repository implements RepositoryInterface, ApiRepositoriesInterface
{
    private $app;
    protected $model;

    public function __construct()
    {
        $this->app = new App();
        $this->makeModel();
    }

    public function makeModel(){
        $model = $this->app->make($this->model());
        if(!$model instanceof Model){
            return false;
        }
        return $this->model = $model;
    }

    abstract public function model();


    /**
     * 根据主键查找数据
     *
     * @param $id
     * @param array $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        return $this->model->find($id, $columns);
    }

    /**
     * 根据指定键与值查找数据
     *
     * @param $attribute
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findBy($attribute, $value, $columns = array('*'))
    {
        return $this->model->where($attribute, '=', $value)->first($columns);
    }

    /**
     * 获取所有数据
     *
     * @param array $columns
     * @return mixed
     */
    public function all($columns = array('*'))
    {
        return $this->model->get($columns);
    }

    /**
     * 预加载
     *
     * @param $relations
     * @return mixed
     */
    public function with($relations)
    {
        return $this->model->with(is_string($relations) ? func_get_args() : $relations);
    }

    /**
     * 批量创建
     *
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * 根据主键更新
     *
     * @param array $data
     * @param $id
     * @param string $attribute
     * @return mixed
     */
    public function update(array $data, $id, $symbol = '=', $attribute = 'id' )
    {
        return $this->model->where($attribute, $symbol, $id)->update($data);
    }

    /**
     * 根据主键删除数据
     *
     * @param $ids
     * @return mixed
     */
    public function delete($ids)
    {
        return $this->model->destroy($ids);
    }

    /**
     * 根据条件新增或修改数据
     *
     * @param $attributes
     * @param $values
     * @return mixed
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        return $this->model->updateOrCreate($attributes, $values);
    }

    /**
     * 获取分页数据
     *
     * @param int $page
     * @param int $perPage
     * @param array $columns
     * @return mixed
     */
    public function paginate($perPage = 20, $columns = array('*'))
    {
        return $this->model->paginate($perPage, $columns);
    }

    /**
     * 根据键排序
     *
     * @param string $attribute
     * @param string $order
     * @return mixed
     */
    function setOrderBy($attribute, $order = 'asc'){
        $this->model = $this->model->orderBy($attribute, $order);
        return $this;
    }

    /**
     * 根据指定键与值查找数据并加锁
     *
     * @param $attribute
     * @param $value
     * @param array $columns
     * @return mixed
     */
    public function findAddLock($value, $attribute = 'id')
    {
        return $this->model->where($attribute, '=', $value)->sharedLock()->first();
    }

    /*
    |--------------------------------------------------------------------------
    | API相关
    |--------------------------------------------------------------------------
    |
    |
    |
    |
    */

    /**
     * 设置状态
     *
     * @param $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    function responseJson($data, $func = 'error'){
        return response()->json(['status' => $this->status, 'type' => $func, $func => $data]);
    }

    /**
     * 没有找到指定数据
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function itemNotFound($message = '指定数据未找到')
    {
        return $this->setStatus(false)->responseJson($message);
    }

    /**
     * 获取状态码
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * 设置状态码
     *
     * @param $statusCode
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * 根据数据类型来产生响应
     *
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function respondWith($data, array $headers = [])
    {
        if (!$data) {
            return $this->errorNotFound('Requested response not found。');
        } elseif ($data instanceof Collection || $data instanceof LengthAwarePaginator || $data instanceof Model) {
            return $this->respondWithItem($data, $headers);
        } elseif (is_string($data) || is_array($data)) {
            return $this->respondWithArray($data, $headers);
        } else {
            return $this->errorInternalError();
        }
    }

    /**
     * 产生响应并处理Collection对象或Eloquent模型
     *
     * @param $item
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithItem($item, array $headers = [])
    {
        $response = response()->json($item->toArray(), $this->statusCode, $headers);
        return $response;
    }

    /**
     * 产生响应并处理数组或字符串
     *
     * @param array $array
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithArray(array $array, array $headers = [])
    {
        $response = response()->json($array, $this->statusCode, $headers);
        return $response;
    }

    /**
     * 产生响应并且返回错误
     *
     * @param $message
     * @param $errorCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondWithError($message, $errorCode)
    {
        return $this->respondWithArray([
            'error' => [
                'code' => $errorCode,
                'http_code' => $this->statusCode,
                'message' => $message
            ]
        ]);
    }

    /**
     * 请求不允许
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorForbidden($message = 'Forbidden')
    {
        return $this->setStatusCode(403)->respondWithError($message, self::CODE_FORBIDDEN);
    }

    /**
     * 服务器内部产生错误
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorInternalError($message = "Internal Error")
    {
        return $this->setStatusCode(500)->respondWithError($message, self::CODE_INTERNAL_ERROR);
    }

    /**
     * 没有找到指定资源
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorNotFound($message = 'Resource Not Found')
    {
        return $this->setStatusCode(404)->respondWithError($message, self::CODE_NOT_FOUND);
    }

    /**
     * 请求授权失败
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorUnauthorized($message = "Unauthorized")
    {
        return $this->setStatusCode(401)->respondWithError($message, self::CODE_UNAUTHORIZED);
    }

    /**
     * 请求错误
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorWrongArgs($message = 'Wrong Arguments')
    {
        return $this->setStatusCode(400)->respondWithError($message, self::CODE_WRONG_ARGS);
    }

    /**
     * 无法处理的请求实体
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorUnprocessableEntity($message = "Unprocessable Entity")
    {
        return $this->setStatusCode(422)->respondWithError($message, self::CODE_UNPROCESSABLE_ENTITY);
    }

    /**
     * 自定义验证数据
     *
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return mixed|void
     */
    public function validate(Request $request, array $rules, array $messages = [], array $customAttributes = [])
    {
        Validator::make($request->all(), $rules, $messages, $customAttributes)->validate();
    }

    /**
     * 自定义验证数据
     *
     * @param array $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return mixed|void
     */
    public function validateArray(array $request, array $rules, array $messages = [], array $customAttributes = [])
    {
        Validator::make($request, $rules, $messages, $customAttributes)->validate();
    }

    /**
     * 标准化参数
     *
     * @param array $reserve
     * @param array $values
     */
    public function normalizeParams(array $reserve, array $values){
        $normalize = array();
        $keys = array_keys($values);
        foreach ($reserve as $key) {
            if(in_array($key, $keys)) {
                if($values[$key] === null) $values[$key] = '';
                $normalize[$key] = $values[$key];
            }
        }
        return $normalize;
    }

    /**
     * 图片解码
     * @param imgStr 待解码数据
     * @param path 解码完成后文件保存路径
     * @return
     */
    public function decoderBase64($imgStr, $path, $suffix = ''){
        $base64_string= explode(',', $imgStr); //截取data:image/png;base64, 这个逗号后的字符
        if(count($base64_string) != 2) return false;
        $length = strpos($base64_string[0], ';') - strpos($base64_string[0], '/');
        $extension = substr($base64_string[0], strpos($base64_string[0], '/') + 1, $length - 1);//获取文件后缀
        if(!in_array($extension, ['png', 'jpeg'])) return false;
        $fileName = $path.DIRECTORY_SEPARATOR.uniqid().$suffix.'.'.$extension;
        Storage::disk('upload')->put($fileName, base64_decode($base64_string[1]));//对截取后的字符使用base64_decode进行解码 写入文件并保存
        return 'upload'.DIRECTORY_SEPARATOR.$fileName;
    }

    /**
     * 获取格式化的随机数  不足位数补零
     * @param len 位数
     * @return 随机数
     */
    function getRandomNum($len){
        $max = pow(10, $len) - 1;
        return sprintf('%0'.$len.'d', random_int(1, $max));
    }

    /**
     * 获取随机码
     * @param digit 位数
     * @return 随机数
     */
    public function randomNumber($digit = 6){
        $str = '';
        $char = '0123456789';
        $char_arr = str_split($char);
        for($i = 0; $i < $digit; $i++){
            $a = array_rand($char_arr, 1);
            $str .= $char_arr[$a];
        }
        return $str;
    }

    /**
     * 根据自增id 生成唯一编号
     * @param prefix 前缀
     * @param num_length 返回长度
     * @param id  自增id
     */
    public function createUniqueNo($id, $num_length, $prefix = ''){
        // 基数
        $base = pow(10, $num_length);
        // 生成数字部分
        $mod = $id % $base;
        $digital = str_pad($mod, $num_length, 0, STR_PAD_LEFT);
        $no = sprintf('%s%s', $prefix, $digital);
        return $no;
    }

    /**
     * 生成唯一订单号
     * @return 订单号
     */
    function buildUniqueNo(){
        return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    /**
     * 数字位数格式化
     * @param digit 位数
     * @return 格式化数字
     */
    function numberFormat($number, $digit = 2){
        return number_format($number, $digit, '.', '');
    }

    /**
     * 检测某个属性值是否已存在
     * @param attribute 字段名称
     * @param value 字段值
     * @param id
     * @return 格式化数字
     */
    function check_exists($attribute, $value, $id = 0){
        return $this->model->where($attribute, $value)->where('id', '!=', $id)->count();
    }

    /**
     * 生成appid
     * @param prefix 前缀
     * @param len 返回长度
     * @return appid
     */
    function buildAppId($prefix = 'zm', $len = 14){
        $app_id = $prefix.sprintf('%08x', dechex(time()));
        for($i = strlen($app_id); $i < $len; $i++){
            $app_id .= dechex(random_int(0, 15));
        }
        return $app_id;
    }

    /**
     * 生成secret
     * @param prefix 前缀
     * @param len 返回长度
     * @return secret
     */
    function buildAppSecret($len = 24,$prefix='ZJ'){
        $str = strrev(sprintf('%08x', dechex(time())));
        $p_arr = str_split($prefix);
        foreach($p_arr as $p){
            $str .= dechex(ord($p));
        }
        for($i = strlen($str); $i < $len; $i++){
            $str .= dechex(random_int(0, 15));
        }
        return substr($str, 0, $len);
    }

    /**
     * 字符串星号隐藏重要部分
     * @param str 原字符串
     * @param pre 头
     * @param back 尾
     * @return 加星后的字符串
     */
    function str_asterisk(string $str, int $pre, int $back)
    {
        $length = strlen($str);
        if($length < $pre + $back) return '';
        $asterisk = substr($str, 0, $pre).str_pad( '*' , $length - $pre - $back , '*').substr($str, -1 * $back);
        return $asterisk;
    }

    function decodeSearchQuery($request){
        $request = (array)$request;
        $search = isset($request['condition']) ? $request['condition'] : '';
        return (array)json_decode($search, true);
    }

}
