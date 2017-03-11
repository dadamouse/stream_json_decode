<?php
class JsonTest extends PHPUnit_Framework_TestCase
{
    static public $list = [];

    public function test_json_grammar()
    {
        //arrange
        $sJson_decode = new stream_json_decode();
        $sJson_decode->setFile('../src/test.json');
        $sJson_decode->row = 1;
        $sJson_decode->callback = array($this, 'callback');
        $sJson_decode->utf8 = false;
        $sJson_decode->debug = false;
        
        //act
        $sJson_decode->json_decode();
        $actual = self::$list;

       $expected = [
          0 => 
          [
            'perfor111' => null,
            'name111' => '裙套裝',
            'haha' => 
              [
              'categoryId' => 111,
              'name' => 'Some category name',
              'eventType' => 'Category Event',
              'haha' => 
                  [
                    'categoryId' => 111,
                    'name' => 'Some category name',
                    'eventType' => 'Category Event',
                  ],
              ],
            'test' => [111, 222, 333],
            'aaaa\\' => 99999,
            'ssss' => 'Any performer name \\',
          ],
          1 => 
          [
            'performerId' => 88888,
            'name' => 'Second performer name',
            'category' => 
            [
              'categoryId' => 88,
              'name' => 'Second Category name',
              'eventType' => 'Category Event 2',
            ],
            'eventType' => 'Performer Event 2',
            'url' => 'http://www.novalidsite.com/somethingelse/performerspage2.html',
            'priority' => 7,
          ],
     ];

        //assert
        $this->assertEquals($expected, $actual);
    }

    static function callback($list)
    {
        self::$list = array_merge(self::$list, $list);
    }
}
