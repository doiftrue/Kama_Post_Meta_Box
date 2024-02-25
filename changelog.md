1.16 — 06.02.2024
- Sanitization on save moved to wordpress hooks.

1.13 — 06.11.2022
- Refactoring and minor improvements.
- `desc` parameter for `sep` field.

1.12.1  — 16.10.2022
- Checkbox field `default` parameter added & minor fixes.

1.12.0  — 15.06.2022
- set_theme() moved to `current_screen` hook to have ability to use get_current_screen() function on `kp_metabox_theme` hook.
- `post_type_options` parameter improvements.
- Fix of `grid` theme for block_editor.
- Code now works only in admin and AJAX - do nothing on front.

1.11.0  — 3.11.2021
- Removed parameter `fields_desc_pos`.
- New: parameters `desc_before`, `desc_after`.
- Refactorion - ability to extend fields class and create your own fields.

1.9.11  — 3.11.2021
- New: theme `grid`.
- New: parameter `fields_desc_pos`. Allows to specify where display description, before or after field. Default before.
- Refactoring.


1.9.10  — 29.11.2020
- Fix: error suppress sign @ was deleted.

1.9.9 — 27.11.2020
- New: `not_post_type` parameter.

1.9.8 — 23.08.2020
- Fix: Неправильно работал JS код поля image.

1.9.7 — 06.03.2019 
- New: Вынес все поля в отдельные методы класса. Теперь класс можно удобно расширять, добавляя свои поля.

1.9.6 — 04.03.2019 
- New: Новые параметры: `post_type_feature` и `post_type_options`.

1.9.5 — 16.12.2018 
- New: Новый синтаксис указать отдельные элементы шаблона. Для типа `image` можно указать тип сохраняемого значения. Мелкие правки кода.

1.9.4 — 28.11.2018 
- New: Новые типы полей `sep` и `image`.

1.9.3 — 16.09.2018 
- New: Новый параметр $meta_key передаваемый фукцнии 'disable_func' для поля.

1.9.2 — 26.04.2018 
- New: Проверка параметра поля 'disable_func' при сохранении поля. 
- New: Новые параметр 'cap' для метабокса и отдельно для полей.

1.9.1 — 2.03.2018  
- New: Новый параметр 'desc' для всего метабокса.
- New: Возможность изменять отдельные поля темы оформления метабокса.
- New: Возможность передать функцию/замыкание в параметр 'desc' у поля.

1.9.0 
- New: Новые параметры полей: 'output_func', 'update_func' и 'disable_func'.

1.8.0 
- Fix: Баг с выводом поля, когда используется своя функция...
- New: Параметр 'sanitize_func' для каждого поля. Чтобы можно было указать очистку отдельного поля.
- Fix: Доработана очистка полей, теперь она опирается на тип поля.

1.7.0 
- New: if set field 'options' parametr it becomes checkbox 'value' attribute.
- Fix: remove extract() call in field() method

1.6.0 
- Fix: closure remove bug

1.5.0 
- New: theme support. New parametr 'theme'. where you can set 'table' or 'line' themes.

1.3.0 
- New: 'disable_func' parametr - its allow to disable/enable metabox by any conditions inside edit post screen.
- Fix: field 'options' parametr for <select> element: now you can set numeric array keys as option value, ex: 'options' => array( 23=>'Name', 5=>'Name 2' )
- Fix: instance ID and instances save algorithm
