<?php

use App\Filament\Resources\Keywords\Schemas\KeywordForm;

it('flattens nested threshold groups into a flat array with group numbers assigned', function () {
    $state = [
        ['conditions' => [
            ['metric' => 'views', 'operator' => '>=', 'value' => 1000],
            ['metric' => 'likes', 'operator' => '>=', 'value' => 50],
        ]],
        ['conditions' => [
            ['metric' => 'likes', 'operator' => '>=', 'value' => 100],
        ]],
    ];

    $result = KeywordForm::flattenThresholdGroups($state);

    expect($result)->toBe([
        ['metric' => 'views', 'operator' => '>=', 'value' => 1000, 'group' => 0],
        ['metric' => 'likes', 'operator' => '>=', 'value' => 50, 'group' => 0],
        ['metric' => 'likes', 'operator' => '>=', 'value' => 100, 'group' => 1],
    ]);
});

it('drops a threshold group whose conditions array is empty', function () {
    $state = [
        ['conditions' => []],
        ['conditions' => [
            ['metric' => 'views', 'operator' => '>=', 'value' => 1000],
        ]],
    ];

    $result = KeywordForm::flattenThresholdGroups($state);

    // 空群組被丟棄後，剩下唯一一組理應重新編號為 group=0，而非保留原本的 index 1。
    expect($result)->toBe([
        ['metric' => 'views', 'operator' => '>=', 'value' => 1000, 'group' => 0],
    ]);
});

it('drops an individual condition whose metric, operator, or value is empty, without discarding the rest of the group', function () {
    // Repeater 的 defaultItems(1) 會預先給一個空白列；使用者新增門檻組後
    // 未實際填寫條件就送出表單時，這種殘缺資料曾在正式環境直接造成
    // SQL 錯誤（value 欄位無預設值）。
    $state = [
        ['conditions' => [
            ['metric' => 'views', 'operator' => '>=', 'value' => 1000],
            ['metric' => null, 'operator' => null, 'value' => null],
        ]],
    ];

    $result = KeywordForm::flattenThresholdGroups($state);

    expect($result)->toBe([
        ['metric' => 'views', 'operator' => '>=', 'value' => 1000, 'group' => 0],
    ]);
});

it('drops an entirely empty threshold group created by clicking "新增門檻組" without filling it in', function () {
    $state = [
        ['conditions' => [
            ['metric' => null, 'operator' => null, 'value' => null],
        ]],
    ];

    $result = KeywordForm::flattenThresholdGroups($state);

    expect($result)->toBe([]);
});

it('returns an empty array when no threshold groups exist', function () {
    expect(KeywordForm::flattenThresholdGroups([]))->toBe([]);
});
