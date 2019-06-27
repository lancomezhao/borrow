<?php
namespace App\Http\Traits;

use App\Exceptions\ErrorException;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait BaseResponse
{

    static $errorMsg = [
        'BAD_REQUEST', //=> 400,请求错误
        'UNAUTHORIZED', //=> 401,未授权
        'FORBIDDEN', //=> 403,禁止访问
        'NOT_FOUND', //=> 404,找不到
        'METHOD_NOT_ALLOWED' //=> 405,不允许的方法
    ];

    private function formatResponse($message, $code, array $data = []){
        $response = [
            'message' => $message,
            'timestamps' => time()
        ];
        $response = array_merge($response, $data);
        return response()->json($response, $code);
    }

    private function throwErrorsException($message, $code){
        throw new ErrorException($message, $code);
    }

    public function responseSuccess($data=[], $message = '操作成功'){
        $code = FoundationResponse::HTTP_OK;
        return $this->formatResponse($message, $code, $data);
    }

    public function responseError($type, $message = '请求地址不存在'){
        $type = in_array($type,self::$errorMsg) ?: 'NOT_FOUND';
        switch($type){
            case 'BAD_REQUEST':
                $code = FoundationResponse::HTTP_BAD_REQUEST;
                break;
            case 'UNAUTHORIZED':
                $code = FoundationResponse::HTTP_UNAUTHORIZED;
                break;
            case 'FORBIDDEN':
                $code = FoundationResponse::HTTP_FORBIDDEN;
                break;
            case 'METHOD_NOT_ALLOWED':
                $code = FoundationResponse::HTTP_METHOD_NOT_ALLOWED;
                break;
            default:
                $code = FoundationResponse::HTTP_NOT_FOUND;
                break;
        }

        $this->throwErrorsException($message, $code);
    }
}
