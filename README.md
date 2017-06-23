本地DEBUG环境：
docker-compose build
docker-compose up

浏览器输入http://localhost/api/User.info返回下面的结果就成功啦：
{
    "error": 1001,
    "message": "未获取用户授权",
    "ext": null
}

本地PMA：
http://localhost:8081/
本地数据库配置：
define ('DB_HOST', 'mysql');
define ('DB_USER', 'root');
define ('DB_PWD', '123456');
define ('DB_DATABASE', 'bochax');
define ('DB_PORT', '3306');
