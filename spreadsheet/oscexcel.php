<?php

namespace OSC\GSE\Spreadsheet;


class OSCExcel 
{
    private $excel;
    private $tables;
    private $exelTable;
    public function __construct($url)
    {
        require_once 'Classes/PHPExcel.php';
        $this->excel = \PHPExcel_IOFactory::load($url);
        foreach ($this->excel->getWorksheetIterator() as $worksheet) {
            // выгружаем данные из объекта в массив
            $this->tables[] = (array) $worksheet->toArray();
        }
    }
   public function getTable($row = null, $column = null)
   {
        $alphabet = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
            'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
        );
        
        foreach ($this->tables[0] as $key => $rows)
        {
            if ($key >= $row)
            {
                foreach($rows as $pos => $cell)
                {
                    if ((int)$pos >= $column)
                    {
                        $this->exelTable[$key][$alphabet[$pos]] = $cell;
                    }
                } 
            }
           
        }
       return $this->exelTable;
   }
}
