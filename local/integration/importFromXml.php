<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

pre('start');
if (file_exists(__DIR__ . '/data/data.xml')) {
    $products = simplexml_load_file(__DIR__ . '/data/data.xml');

    foreach ($products as $product) {
        pre('Товар:');
        echo '<b>Название:</b> ' . $product->NAME . '<br />';
        echo '<b>Описание:</b> ' . $product->DESCRIPTION . '<br />';
        echo '<b>Выбор цвета сукна:</b> <br />';
        foreach ($product->SYKNO->VARIANT as $syknoColor) {
            echo($syknoColor['VALUE']) . '<br />';
        }
        echo '<b>Размер поля:</b> <br />' . $product->OFFERS->OFFER->SIZE_FIELD . '<br />';
        echo '<b>Формат игры:</b> <br />' . $product->OFFERS->OFFER->GAME_TYPE . '<br />';
        echo '<b>Материал стола:</b> <br />' . $product->OFFERS->OFFER->TABLE_MATERIAL . '<br />';
        echo '<b>Тип стола:</b> <br />' . $product->OFFERS->OFFER->TABLE_TYPE . '<br />';
        echo '<b>Количество ножек:</b> <br />' . $product->OFFERS->OFFER->QTY_LEGS . '<br />';
        echo '<b>Вес стола:</b> <br />' . $product->OFFERS->OFFER->VES . '<br />';
        echo '<b>Цена:</b> <br />' . $product->OFFERS->OFFER->PRICE . ' руб.<br />';
        echo '<b>Артикул:</b> <br />' . $product->OFFERS->OFFER->ART . '<br />';
        pre('--------------------------------');
    }
} else {
    exit('Не удалось открыть файл data.xml.');
}
pre('done.');