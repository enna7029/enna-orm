<?php
declare(strict_types=1);

namespace Enna\Orm;

use Enna\Framework\Helper\Collection;
use Closure;
use Enna\Orm\Paginator\Driver\Bootstrap;
use ArrayIterator;
use JsonSerializable;

abstract class Paginator implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * 是否简洁模式
     * @var bool
     */
    protected $simple = false;

    /**
     * 数据集
     * @var Collection
     */
    protected $items;

    /**
     * 当前页
     * @var int
     */
    protected $currentPage;

    /**
     * 最后一页
     * @var int
     */
    protected $lastPage;

    /**
     * 总数
     * @var int
     */
    protected $total;

    /**
     * 每页数量
     * @var int
     */
    protected $listRows;

    /**
     * 是否还有下一页
     * @var bool
     */
    protected $hasMore;

    protected $options = [
        'var_page' => 'page',
        'path' => '/',
        'query' => [],
        'fragment' => 15,
    ];

    /**
     * 自定义获取当前页码的闭包
     * @var Closure
     */
    protected static $currentPageResolver;

    /**
     * 自定义获取当前路径的闭包
     * @var Closure
     */
    protected static $currentPathResolver;

    /**
     * 自定义分页驱动的闭包
     * @var Closure
     */
    protected static $maker;

    public function __construct($items, int $listRows, int $currentPage = 1, int $total = null, bool $simple = false, array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->options['path'] = $this->options['path'] != '/' ? rtrim($this->options['path'], '/') : $this->options['path'];

        $this->simple = $simple;
        $this->listRows = $listRows;

        if (!$items instanceof Collection) {
            $items = Collection::make($items);
        }

        if ($simple) {
            $this->currentPage = $this->setCurrentPage($currentPage);
            $this->hasMore = count($items) > $this->listRows;
            $items = $items->slice(0, $this->listRows);
        } else {
            $this->total = $total;
            $this->lastPage = (int)ceil($total / $listRows);
            $this->currentPage = $this->setCurrentPage($currentPage);
            $this->hasMore = $this->currentPage < $this->lastPage;
        }

        $this->items = $items;
    }

    public static function make($items, int $listRows, int $currentPage = 1, int $total = null, bool $simple = false, array $options = [])
    {
        if (isset(static::$maker)) {
            return call_user_func(static::$maker, $items, $listRows, $currentPage, $total, $simple, $options);
        }

        return new Bootstrap($items, $listRows, $currentPage, $total, $simple, $options);
    }

    /**
     * Note: 设置分页驱动闭包
     * Date: 2023-04-17
     * Time: 11:02
     * @param Closure $resolver
     * @return void
     */
    public static function maker(Closure $resolver)
    {
        static::$maker = $resolver;
    }

    /**
     * Note: 获取当前页码
     * Date: 2023-04-14
     * Time: 18:40
     * @param string $varPage 当前页面变量
     * @param int $default 默认值
     * @return false|int|mixed
     */
    public static function getCurrentPage(string $varPage = 'page', int $default = 1)
    {
        if (isset(static::$currentPageResolver)) {
            return call_user_func(static::$currentPageResolver, $varPage);
        }

        return $default;
    }

    /**
     * Note: 设置当前页码闭包
     * Date: 2023-04-14
     * Time: 18:39
     * @param Closure $callback
     * @return void
     */
    public static function currentPageResolver(Closure $callback)
    {
        static::$currentPageResolver = $callback;
    }

    /**
     * Note: 自动获取当前的path
     * Date: 2023-04-17
     * Time: 10:32
     * @param string $default
     * @return false|mixed|string
     */
    public static function getCurrentPath($default = '/')
    {
        if (isset(static::$currentPathResolver)) {
            return call_user_func(static::$currentPathResolver);
        }

        return $default;
    }

    /**
     * Note: 设置当前路径闭包
     * Date: 2023-04-17
     * Time: 10:27
     * @param Closure $callback
     * @return void
     */
    public static function currentPathResolver(Closure $callback)
    {
        static::$currentPathResolver = $callback;
    }

    /**
     * Note: 设置当前页码
     * Date: 2023-04-17
     * Time: 14:30
     * @param int $currentPage 页码
     * @return int
     */
    protected function setCurrentPage($currentPage)
    {
        if (!$this->simple && $currentPage > $this->lastPage) {
            return $this->lastPage > 0 ? $this->lastPage : 1;
        }

        return $currentPage;
    }

    /**
     * Note: 获取总数量
     * Date: 2023-04-17
     * Time: 15:42
     * @return int|null
     */
    public function total()
    {
        if ($this->simple) {
            throw new \DomainException('no support total');
        }
        return $this->total;
    }

    /**
     * Note: 获取每页数量
     * Date: 2023-04-17
     * Time: 15:42
     * @return int
     */
    public function listRows()
    {
        return $this->listRows;
    }

    /**
     * Note: 获取当前页
     * Date: 2023-04-17
     * Time: 15:43
     * @return int
     */
    public function currentPage()
    {
        return $this->currentPage;
    }

    /**
     * Note: 给每个元素执行回调
     * Date: 2023-04-17
     * Time: 15:41
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this->items as $key => $item) {
            $result = $callback($item);

            if ($result === false) {
                break;
            } elseif (!is_object($item)) {
                $this->items[$key] = $result;
            }
        }

        return $this;
    }

    public function offsetExists()
    {
        return $this->items->offsetExists();
    }

    public function offsetSet()
    {
        return $this->items->offsetSet();
    }

    public function offsetGet()
    {
        return $this->items->offsetGet();
    }

    public function offsetUnset()
    {
        return $this->items->offsetUnset();
    }

    public function count()
    {
        return $this->items->count();
    }

    public function getIterator()
    {
        return new ArrayIterator($this->items->all());
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Note: 转为数组
     * Date: 2023-04-17
     * Time: 15:21
     * @return array
     */
    public function toArray()
    {
        try {
            $total = $this->total();
        } catch (\DomainException $e) {
            $total = null;
        }

        return [
            'total' => $total,
            'per_page' => $this->listRows(),
            'current_page' => $this->currentPage(),
            'last_page' => $this->lastPage,
            'data' => $this->items->toArray(),
        ];
    }

    public function __call($name, $arguments)
    {
        $result = call_user_func_array([$this->items, $name], $arguments);

        if ($result instanceof Collection) {
            $this->items = $result;
            return $this;
        }

        return $result;
    }
}