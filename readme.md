#Шаблоны в SQL

Язык TWIG (Django-style) довольно гибок и его (язык, а не шаблонизатор) вполне можно использовать в разного рода шаблончиках и шаблонищах. К примеру - его можно использовать при построении SQL запросов.

##немного истории, или чем остальные SQL билдеры плохи.

Кратко - остальные SQL-построители медленны, а некоторые неудобны. Ну и некоторые такие и такие. Их мы рассматривать не будем.

Надо рассмотреть pdo - быстрый, но неудобный и DBSimple - удобный, но небыстрый.

##Основная мысль

Нам нужно транслировать наш SQL в функцию, которая уже выведет нам готовый запрос с заполненными полями и отескейпленными переменными. Примерно так, как оно делается в php, если писать в индусском стиле. Тоесть, некий класс, который возьмет на себя задачу построить нам функцию, как будто мы ее писали сами, на php. Встречайте, это он!

По дороге появляется возможность кэшировать результат. Если вы исполняете один и тот-же запрос с разными параметрами снова и снова, он сохраняется в кэше и не транслируется заново. Если вы используете механизм кэширования, оттранслированная строка-тело функции может сохраняться в кэше и не транслироваться заново (пока не сделано).

## Идеология и некоторый идеи, проблемы и решения

Практика показала, что пользоваться статическими классами неудобно. Так что класс полностью динамический, без синглтонов и прочей ненужности.

Шаблонизатор позволяет простенько вставлять константные строки в готовый результат. Константа должна быть определена на момент первой трансляции sql и не меняться в процессе повторного исполнения. При этом не производится ескейпинга и вообще ничего дополнительного. Применение - имена таблиц и префикс таблиц.

регистрация константных строк производится функцией

 - sql->regcns('prefix','myprefix')

Можно вставлять переменные - они передаются указателем в функцию regvar. В этом случае возможны все операции и все фильтры, которые применяются к переменным-вопросикам. В функции-порождении на место этой переменной будет вставлено что-то вроде sql_template::variables[25]  (пока не сделано)

## язык шаблонов

Язык похож на twig, так что основным "шаблонным" маркером являетются двойные фигурные скобки `{{}}`.

В этих скобках можно указывать параметр, передаваемый в аргументах. Вопросительный знак будет заменен на следующий аргумент. Если за знаком идет номер - будет использоваться этот, по номеру, аргумент.

    select * from index where {{?|int}}<`field`;

в этом запросе первый аргумент будет вставлен в запрос, при этом он будет проверен на целое число.

    insert into xxx ({{?|keys|join(",")}}) values ({{?1|values|join(",")}});

в этом запросе первый аргумент является ассоциативным массивом. В первой части его ключи будут перечислены через запятую, во второй половине то же самое произойдет со значениями.

## имеющиеся фильтры

- int - проверка параметра на "целость". Escape при этом отменяется.
- float - проверка параметра на "вещественность". Escape при этом отменяется
- noescape - отменить Escape при выводе параметра, если он уже был отэскейплен ранее.
- keys - получить список ключей массива. Все параметры, не являющиеся целыми числами будут заэскейплены.
- values - получить список значений массива. Все параметры, не являющиеся целыми числами будут заэскейплены.
- join - объединить значения массива, используя символ-разделитель. Символ должен быть в кавычках.
- pair - оформление пары ```поле``='значение'`
- format(FORMAT,SEPARATOR) - с каждым элементом массива и парами ключ-значение выполняется sprintf с таким форматом. Если нужно поменять местами параметры в формате - курите описание строки формата, оно это позволяет. После этой опреации выполняется array_join с сеператором и получившимся массивом строк.


##benchmarking

Сравнивать шаблонизатор, вероятно, нужно с ближайшим конкурентом - pdo.

Сравниваются такие операции

    for($i=0;$i<self::MAX_REPEAT_NUMBER;$i++){
        $func = $this->sql->parse('set names {{?}} ; -- comment '.$i);
        $this->pdo->exec($func('utf8'));
    }

и

    for($i=0;$i<self::MAX_REPEAT_NUMBER;$i++){
        $sth = $this->pdo->prepare('set names :code; -- comment'.$i);
        $sth->bindValue(':code','utf8', PDO::PARAM_STR);
        $sth->execute();
    }

Тоесть, 1000 раз выполняется запрос `set names utf8` оттранслированный шаблонизатором и отпрепарированный pdo.
Результат выполнения - шаблонизатор проигрывает, примерно  0.644124 против 0.477978 секунд. Впрочем, результат предсказуем. Парсинг в pdo делается не в php. Хотя могли бы выиграть и побольше.

Другой тест, "отпрепарированные" запросы, показывает значительно более забавные результаты

    $func = $this->sql->parse('set names {{?}} ;');
    $time=microtime(true);
    for($i=0;$i<self::MAX_REPEAT_NUMBER;$i++){
        $this->pdo->exec($func('utf8'));
    }
    $time1=microtime(true)-$time;

и

    $sth = $this->pdo->prepare('set names :code');
    $time=microtime(true);
    for($i=0;$i<self::MAX_REPEAT_NUMBER;$i++){
        $sth->bindValue(':code','utf8', PDO::PARAM_STR);
        $sth->execute();
    }
    $time2=microtime(true)-$time;

На 1000 выполнений приходится 0.330789 против 0.353897 секунд. Выходит, что шаблонизатор + exec даже несколько эффективнее, чем prepare+BindParam+execute для большого количества однотипных запросов.