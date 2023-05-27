<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 *
 * 自动下载外链文章
 * 插件简单加密，如有问题，欢迎来访本人博客留言
 *
 * @package AutoSavePicture
 * @author cultureSun
 * @version 1.0.0
 * @link https://culturesun.site
 */
class AutoSavePicture_Plugin implements Typecho_Plugin_Interface
{

    /**
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->write = array('AutoSavePicture_Plugin', 'saveFile');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->write = array('AutoSavePicture_Plugin', 'saveFile');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 下载保存远程图片
     *
     * @access public
     * @param array $post 数据结构体
     * @return array
     */
    public static function saveFile($post)
    {
        $text = $post['text'];
        $urls = self::getImageFromText($text);
        if (!isset($urls) || count($urls) == 0) return $post;
        if (!isset($_POST["attachment"])) $_POST["attachment"] = array();
        foreach ($urls as $url) {
            $path = self::get_remote_file($url);
            $text = self::replace($text, $url, $path);
        }
        $post['text'] = $text;
        return $post;
    }

    static function getImageFromText($text)
    {
        $patten = '/\!\[(.*)\]\((http.+)\)/';
        preg_match_all($patten, $text, $arr);
        $result = [];
        if (isset($arr) && count($arr[2]) > 0) {
            foreach ($arr[2] as $value) {
                if (!in_array($value, $result)) {
                    if (strpos($value, $_SERVER['HTTP_HOST']) !== false || strncmp($value, 'http', 4) !== 0) {
                        continue;
                    }
//                    if (strpos($value, 'https://mmbiz.qpic.cn/') !== false) {
//                        $result[] = preg_replace('/https/', 'http', $value, 1);
//                    } else {}
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    static function replace($text, $url, $path)
    {
        $text = str_ireplace($url, Helper::options()->siteUrl . substr($path, 1), $text);
        return $text;
    }

    static function get_remote_file($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); // URL地址
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 返回响应结果而不是输出
        curl_setopt($ch, CURLOPT_HEADER, false); // 不包含响应头
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 不验证SSL证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 不验证SSL主机名
        $response = curl_exec($ch);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 关闭CURL
        curl_close($ch);
        if ($response_code == 200) {
            $parse = parse_url($url);
            if (strpos($url, '://mmbiz.qpic.cn/') !== false) {
                       $result = \Widget\Upload::alloc()->uploadHandle(['name' => $_POST['title'] . '_' . rand(1, 500) . '.png', 'type' => 'image/png', 'bits' => $response]);
                
             $struct = [
                'title' => $result['name'],
                'slug' => $result['name'],
                'type' => 'attachment',
                'status' => 'publish',
                'text' => serialize($result),
                'allowComment' => 1,
                'allowPing' => 0,
                'allowFeed' => 1,
                'parent' => (int)$_POST['cid'] ?? 0
            ];
            $insertId = \Widget\Upload::alloc()->insert($struct);
            array_push($_POST["attachment"], $insertId);
            return $result['path'];
        } else {
            return false;
        }
    }
}
