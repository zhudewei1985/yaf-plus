<?php

/**
 *  输出JSON/buffer等
 *
 * @author: ZDW
 */
class Helper_Response
{
    /**
     *   生成JSON格式的正确消息
     *
     * @access  public
     * @param
     * @return  void
     */
    public static function jsonResult($content, $message = '', $append = array())
    {
        self::jsonResponse($content, 0, $message, $append);
    }

    /**
     * 创建一个JSON格式的错误信息
     *
     * @access  public
     * @param   string $msg
     * @return  void
     */
    public static function jsonError($msg)
    {
        self::jsonResponse('', 1, $msg);
    }

    /**
     * 创建一个JSON格式的数据
     *
     * @access  public
     * @param   string $content
     * @param   integer $error
     * @param   string $message
     * @param   array $append
     * @return  void
     */
    private static function jsonResponse($content = '', $error = "0", $message = '', $append = array())
    {

        $res = array('error' => $error, 'message' => $message, 'content' => $content);
        if (!empty($append)) {
            foreach ($append AS $key => $val) {
                $res[$key] = $val;
            }
        }
        $val = json_encode($res);
        //Jquery + Zeptojs jsonp
        if (isset($_GET['jsoncallback'])) {
            $val = $_GET['jsoncallback'] . '(' . $val . ')';
            exit($val);
        } elseif (isset($_GET['callback'])) {
            $val = $_GET['callback'] . '(' . $val . ')';
            exit($val);
        }
        exit($val);
    }

    /**
     *  API接口：生成JSON格式的正确消息
     * @param string $data 数据
     * @param string $msg 提示消息
     * @param array $append
     */
    public static function apiJsonResult($data, $msg = '', $append = array())
    {
        self::apiJsonResponse($data, '200', $msg, $append);
    }

    /**
     *  API接口：创建一个JSON格式的错误信息
     * @param string $error 错误代码
     * @param string $msg 提示消息
     */
    public static function apiJsonError($error, $msg)
    {
        self::apiJsonResponse('', $error, $msg);
    }

    /**
     * 创建一个JSON格式的数据
     *
     * @access  public
     * @param   string $data
     * @param   integer $error
     * @param   string $msg
     * @return  void
     */
    private static function apiJsonResponse($data = '', $error = '200', $msg = '', $append = array())
    {

        $res = array('error' => $error, 'msg' => $msg, 'data' => $data);
        if (!empty($append)) {
            foreach ($append AS $key => $val) {
                $res[$key] = $val;
            }
        }
        $val = json_encode($res);
        //Jquery + Zeptojs jsonp
        if (isset($_GET['jsoncallback'])) {
            $val = $_GET['jsoncallback'] . '(' . $val . ')';
            exit($val);
        } elseif (isset($_GET['callback'])) {
            $val = $_GET['callback'] . '(' . $val . ')';
            exit($val);
        }
        exit($val);
    }

    /**
     *  protobuf：返回提示消息
     * @param string $code 错误代码
     * @param string $msg 提示消息
     */
    public static function protobufResponse($code, $msg)
    {
        if (!headers_sent()) {
            header('Content-Type:application/octet-stream');
            header('code:'.intval($code));
        }
        $pbres = new Proto_ErrorModel();
        $pbres->setCode(intval($code));
        $pbres->setMsg($msg);
        echo $pbres->serializeToString();
        exit();
    }
}