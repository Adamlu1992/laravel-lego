<?php namespace Lego\Widget;

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Request;
use Lego\Register\HighPriorityResponse;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;

class Grid extends Widget
{
    protected $filter;

    protected function transformer($data)
    {
        if ($data instanceof Filter) {
            $this->filter = $data;
            $this->filter->processFields();
            $this->filter->process();
            return $this->filter->data();
        }

        return parent::transformer($data);
    }

    public function orderBy($attribute, bool $desc = false)
    {
        $this->query->orderBy($attribute, $desc);
    }

    /**
     * 导出功能
     * @var array
     */
    private $exports = [];

    public function exports()
    {
        return $this->exports;
    }

    public function export($name, \Closure $onExport = null)
    {
        /** @var \Lego\Register\HighPriorityResponse $resp */
        $resp = lego_register(
            HighPriorityResponse::class,
            function () use ($name, $onExport) {
                if ($onExport) {
                    call_user_func($onExport, $this);
                }
                return $this->exportAsExcel($name);
            },
            md5('grid export' . $name)
        );
        $this->exports[$name] = $resp->url();

        return $this;
    }

    private function exportAsExcel($filename)
    {
        $data = [];
        foreach ($this->paginator() as $store) {
            $_row = [];
            foreach ($this->fields() as $field) {
                $_row[$field->description()] = $store->get($field->name());
            }
            $data [] = $_row;
        }

        return Excel::create(
            $filename,
            function (LaravelExcelWriter $excel) use ($data) {
                $excel->sheet('SheetName',
                    function (\PHPExcel_Worksheet $sheet) use ($data) {
                        $sheet->fromArray($data);
                    }
                );
            }
        )->export('xls');
    }

    /**
     * @var AbstractPaginator
     */
    private $paginator;

    /**
     * how many rows per page
     * @var int
     */
    private $paginatorPerPage = 100;
    private $paginatorPageName;

    public function paginate(int $perPage, $pageName = null)
    {
        $this->paginatorPerPage = $perPage;
        $this->paginatorPageName = $pageName;

        return $this;
    }

    public function paginator()
    {
        if (!$this->paginator) {
            $this->paginator = $this->query->paginate(
                $this->paginatorPerPage,
                null,
                $this->paginatorPageName
            );
            $this->paginator->appends(Request::input());
        }

        return $this->paginator;
    }

    /**
     * Widget 的所有数据处理都放在此函数中, 渲染 view 前调用
     */
    public function process()
    {
        foreach ($this->exports as $name => $url) {
            $this->addButton(self::BTN_RIGHT_TOP, $name, $url, 'lego-export-' . $name);
        }

        $this->paginator();
    }

    /**
     * 渲染当前对象
     * @return string
     */
    public function render()
    {
        return view('lego::default.grid.table', ['grid' => $this])->render();
    }
}
