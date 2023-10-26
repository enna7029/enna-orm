<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use DateTime;
use DateTimeInterface;
use DateInterval;
use Enna\Orm\Exception\InvalidArgumentException;

/**
 * 缓存item类
 * Class CacheItem
 * @package Enna\Orm\Db
 */
class CacheItem
{
    /**
     * 缓存Key
     * @var string
     */
    protected $key;

    /**
     * 缓存内容
     * @var mixed
     */
    protected $value;

    /**
     * 过期时间
     * @var int|DateTimeInterface
     */
    protected $expire;

    /**
     * 缓存tag
     * @var string
     */
    protected $tag;

    /**
     * 是否命中缓存
     * @var bool
     */
    protected $isHit = false;

    public function __construct(string $key = null)
    {
        $this->key = $key;
    }

    /**
     * Note: 为缓存项设置key
     * Date: 2023-03-22
     * Time: 10:53
     * @param string $key
     * @return $this
     */
    public function setKey(string $key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Note: 获取当前缓存项的key
     * Date: 2023-03-22
     * Time: 10:55
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Note: 为当前项设置标签
     * Date: 2023-03-22
     * Time: 14:17
     * @param string $tag
     * @return $this
     */
    public function tag($tag = null)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Note: 获取缓存Tag
     * Date: 2023-03-22
     * Time: 11:20
     * @return string|array
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Note: 为此缓存设置值
     * Date: 2023-03-22
     * Time: 14:20
     * @param mixed $value
     * @return $this
     */
    public function set($value)
    {
        $this->value = $value;
        $this->isHit = true;

        return $this;
    }

    /**
     * Note: 获取缓存项
     * Date: 2023-03-22
     * Time: 11:46
     * @return mixed
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Note: 确认缓存项的检查是否命中
     * Date: 2023-03-22
     * Time: 14:21
     * @return bool
     */
    public function isHit()
    {
        return $this->isHit;
    }

    /**
     * Note: 返回当前缓存项的有效期
     * Date: 2023-03-22
     * Time: 11:48
     * @return DateTimeInterface|int|null
     */
    public function getExpire()
    {
        if ($this->expire instanceof DateTimeInterface) {
            return $this->expire;
        }

        return $this->expire ? $this->expire - time() : null;
    }

    /**
     * Note: 设置缓存项的有效期
     * Date: 2023-03-22
     * Time: 11:11
     * @param mixed $expire 有效期
     * @return $this
     */
    public function expire($expire)
    {
        if (is_null($expire)) {
            $this->expire = null;
        } elseif ($expire instanceof DateTimeInterface) {
            $this->expire = $expire;
        } elseif (is_numeric($expire) || $expire instanceof DateInterval) {
            $this->expiresAfter($expire);
        } else {
            throw new InvalidArgumentException('not support datetime');
        }

        return $this;
    }

    /**
     * Note: 设置缓存项的有效期
     * Date: 2023-03-22
     * Time: 11:16
     * @param int|DateInterval $dateInterval 有效期
     * @return $this
     */
    protected function expiresAfter($dateInterval)
    {
        if ($dateInterval instanceof DateInterval) {
            $this->expire = DateTime::createFromFormat('U', (string)time())->add($dateInterval)->format('U');
        } elseif (is_numeric($dateInterval)) {
            $this->expire = $dateInterval + time();
        } else {
            throw new InvalidArgumentException('not support datetime');
        }

        return $this;
    }

    /**
     * Note: 设置缓存项的准确过期时间点
     * Date: 2023-10-16
     * Time: 17:02
     * @param DateTimeInterface $expiration
     * @return $this
     */
    public function expiresAt($expiration)
    {
        if ($expiration instanceof DateTimeInterface) {
            $this->expire = $expiration;
        } else {
            throw new InvalidArgumentException('not support datetime');
        }

        return $this;
    }
}