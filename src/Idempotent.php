<?php

namespace PFinal\Idempotent;

/**
 * 幂等
 *
 * CREATE TABLE `idempotent` (
 *     `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
 *     `seq` varchar(50) NOT NULL DEFAULT '' COMMENT '请求序号',
 *     `response` longtext NOT NULL DEFAULT '' COMMENT '响应内容',
 *     `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     PRIMARY KEY (`id`),
 *     UNIQUE KEY `ind_seq` (`seq`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT '幂等';
 *
 */
class Idempotent
{
    /**
     * @return int
     */
    protected static function expire()
    {
        return 60 * 60 * 24; //1天
    }

    public static function tableName()
    {
        return '{{%idempotent}}';
    }

    /**
     * 以幂等方式调用，同一请求，多次调用，返回结果相同
     *
     * @param string $seq 序列号 唯一标识一个请求
     * @param callable $callback
     * @return string
     */
    public static function run($seq, callable $callback)
    {
        //清理过期数据
        static::clear();

        //使用一个新的连接开启事务,避免事务干扰到业务代码
        $connect = static::getDbConnect();

        //先检查是否有响应数据
        $rows = $connect->query('SELECT response FROM ' . static::tableName() . ' WHERE seq = ?', [$seq]);
        if (count($rows) > 0) {
            //Log::debug("repeated: " . $seq);
            return unserialize($rows[0]['response']);
        }

        $connect->beginTransaction();

        try {

            $connect->execute('INSERT INTO ' . static::tableName() . ' (seq, response) VALUES (?, ?)', [$seq, '']);

        } catch (\Exception $ex) {

            //insert 失败，并发的请求或许已执行成功，退出事务后，从新查询一次

            $connect->rollBack();

            $rows = $connect->query('SELECT response FROM ' . static::tableName() . ' WHERE seq = ?', [$seq]);
            if (count($rows) > 0) {
                //Log::debug("repeated: " . $seq);
                return unserialize($rows[0]['response']);
            } else {

                //如果查不到，则说明是因为别的原因insert失败
                //Log::warning($ex->getMessage());
                return json_encode(['status' => false, 'data' => $ex->getMessage()]);
            }
        }

        try {

            //执行业务代码
            $response = call_user_func($callback);

        } catch (\Exception $ex) {

            //业务代码执行失败
            //Log::warning($ex->getMessage());
            $response = json_encode(['status' => false, 'data' => $ex->getMessage()]);
        }

        $connect->execute('UPDATE ' . static::tableName() . ' set response=? where seq = ?', [serialize($response), $seq]);

        $connect->commit();

        return $response;
    }


    /**
     * @var \PFinal\Database\Connection
     */
    protected static $dbConnect;

    public static $config;

    protected static function getDbConnect()
    {
        if (static::$dbConnect == null) {
            static::$dbConnect = new \PFinal\Database\Connection(static::$config['db.config']);
        }

        return static::$dbConnect;
    }

    /**
     * 清理过期数据
     */
    protected static function clear()
    {
        //概率为0.1%
        if (mt_rand(0, 1000000) < 1000) {
            //只保留24小时以内数据
            $time = date('Y-m-d H:i:s', time() - static::expire());

            static::getDbConnect()->execute('DELETE FROM ' . static::tableName() . ' where created_at < ?', [$time]);
        }
    }
}