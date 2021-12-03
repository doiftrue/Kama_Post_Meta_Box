<?php

if( class_exists('Kama_Post_Meta_Box') ){
	return;
}

/**
 * Создает блок произвольных полей для указанных типов записей.
 *
 * Возможные параметры класса, смотрите в: Kama_Post_Meta_Box::__construct()
 * Возможные параметры для каждого поля, смотрите в: Kama_Post_Meta_Box::field()
 *
 * При сохранении, очищает каждое поле, через: wp_kses() или sanitize_text_field().
 * Функцию очистки можно заменить через хук 'kpmb_save_sanitize_{$id}' и
 * также можно указать название функции очистки в параметре 'save_sanitize'.
 * Если указать функции очистки и в параметре, и в хуке, то будут работать обе!
 * Обе функции очистки получают данные: $metas - все метаполя, $post_id - ID записи.
 *
 * Блок выводиться и метаполя сохраняются для юзеров с правом редактировать текущую запись.
 *
 * PHP: 7+
 *
 * @changlog https://github.com/doiftrue/Kama_Post_Meta_Box/blob/master/changelog.md
 *
 * @version 1.9.11
 */
class Kama_Post_Meta_Box {

	use Kama_Post_Meta_Box__Fields;
	use Kama_Post_Meta_Box__Themes;

	public $opt;

	public $id;

	static $instances = array();

	/**
	 * Конструктор.
	 *
	 * @param array $opt {
	 *     Опции по которым будет строиться метаблок.
	 *
	 *     @type string       $id                 Иднетификатор блока. Используется как префикс для названия метаполя.
	 *                                            Начните с '_' >>> '_foo', чтобы ID не был префиксом в названии метаполей.
	 *     @type string       $title              Заголовок блока.
	 *     @type string       $desc               Описание для самого метабокса (сразу под заголовком).
	 *     @type string $fields_desc_pos          Где располагать описание для отдельного поля. before, after. По умолчанию: before.
	 *     @type string       $post_type          Описание для метабокса. Можно указать функцию/замыкание, она получит $post.
	 *     @type string|array $not_post_type      Строка/массив. Типы записей для которых добавляется блок: `[ 'post', 'page' ]`.
	 *                                            По умолчанию: '' = для всех типов записей.
	 *     @type string       $post_type_feature  Строка. Возможность которая должна быть у типа записи,
	 *                                            чтобы метабокс отобразился. See https://wp-kama.ru/post_type_supports
	 *     @type string       $post_type_options  Массив. Опции типа записи, которые должны быть у типа записи,
	 *                                            чтобы метабокс отобразился. See перывый параметр https://wp-kama.ru/get_post_types
	 *     @type string       $priority           Приоритет блока для показа выше или ниже остальных блоков ('high' или 'low').
	 *     @type string       $context            Место где должен показываться блок ('normal', 'advanced' или 'side').
	 *     @type string       $disable_func       Функция отключения метабокса во время вызова самого метабокса.
	 *                                            Если вернет что-либо кроме false/null/0/array(), то метабокс будет отключен.
	 *                                            Передает объект поста.
	 *     @type string       $cap                Название права пользователя, чтобы показывать метабокс.
	 *     @type string       $save_sanitize      Функция очистки сохраняемых в БД полей. Получает 2 параметра:
	 *                                            $metas - все поля для очистки и $post_id.
	 *     @type string       $theme              Тема оформления: 'table', 'line', 'grid'.
	 *                                            ИЛИ массив паттернов полей:
	 *                                            css, fields_wrap, field_wrap, title_patt, field_patt, desc_patt.
	 *                                            ЕСЛИ Массив указывается так: [ 'desc_patt' => '<div>%s</div>' ]
	 *                                            (за овнову будет взята тема line).
	 *                                            ЕСЛИ Массив указывается так: [ 'table' => [ 'desc_patt' => '<div>%s</div>' ] ]
	 *                                            (за овнову будет взята тема table).
	 *                                            ИЛИ изменить тему можно через фильтр 'kp_metabox_theme'
	 *                                            (удобен для общего изменения темы для всех метабоксов).
	 *     @type array        $fields {
	 *         Метаполя. Собственно, сами метаполя. Список возможных ключей массива для каждого поля.
	 *         See метод field().
	 *
	 *         @type string $type            Тип поля: textarea, select, checkbox, radio, image, wp_editor, hidden, sep_*.
	 *                                       Или базовые: text, email, number, url, tel, color, password, date, month, week, range.
	 *                                       'sep' - визуальный разделитель, для него нужно указать `title` и можно
	 *                                       указать `'attr'=>'style="свои стили"'`.
	 *                                       'sep' - чтобы удобнее указывать тип 'sep' начните ключ поля с
	 *                                       `sep_`: 'sep_1' => [ 'title'=>'Разделитель' ].
	 *                                       Для типа `image` можно указать тип сохраняемого значения в
	 *                                       `options`: 'options'=>'url'. По умолчанию тип = id.
	 *                                       По умолчанию 'text'.
	 *         @type string $title           Заголовок метаполя.
	 *         @type string $desc            Описание для поля. Можно указать функцию/замыкание, она получит параметры:
	 *                                       $post, $meta_key, $val, $name.
	 *         @type string $placeholder     Атрибут placeholder.
	 *         @type string $id              Атрибут id. По умолчанию: $this->opt->id .'_'. $key.
	 *         @type string $class           Атрибут class: добавляется в input, textarea, select.
	 *                                       Для checkbox, radio в оборачивающий label.
	 *         @type string $attr            Любая строка, будет расположена внутри тега. Для создания атрибутов.
	 *                                       Пр: style="width:100%;".
	 *         @type string $val             Значение по умолчанию, если нет сохраненного.
	 *         @type string $options         массив: array('значение'=>'название') - варианты для типов 'select', 'radio'.
	 *                                       Для 'wp_editor' стенет аргументами.
	 *                                       Для 'checkbox' станет значением атрибута value: <input type="checkbox" value="{options}">.
	 *                                       Для 'image' определяет тип сохраняемого в метаполе значения:
	 *                                       id (ID вложения), url (url вложения).
	 *         @type string $callback        Название функции, которая отвечает за вывод поля.
	 *                                       Если указана, то ни один параметр не учитывается и за вывод полностью отвечает указанная функция.
	 *                                       Все параметры передаются ей... Получит параметры: $args, $post, $name, $val
	 *         @type string $sanitize_func   Функция очистки данных при сохранении - название функции или Closure.
	 *                                       Укажите 'none', чтобы не очищать данные...
	 *                                       Работает, только если не установлен глобальный параметр 'save_sanitize'...
	 *                                       Получит параметр $value - сохраняемое значение поля.
	 *         @type string $output_func     Функция обработки значения, перед выводом в поле.
	 *                                       Получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.
	 *         @type string $update_func     Функция сохранения значения в метаполя.
	 *                                       Получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.
	 *         @type string $disable_func    Функция отключения поля.
	 *                                       Если не false/null/0/array() - что-либо вернет, то поле не будет выведено.
	 *                                       Получает парамтры: $post, $meta_key
	 *         @type string $cap             Название права пользователя, чтобы видеть и изменять поле.
	 *     }
	 *
	 * }
	 */
	function __construct( $opt ){

		$defaults = [
			'id'                => '',
			'title'             => '',
			'desc'              => '',
			'fields_desc_pos'   => 'before',
			'post_type'         => '',
			'not_post_type'     => '',
			'post_type_feature' => '',
			'post_type_options' => '',
			'priority'          => 'high',
			'context'           => 'normal',
			'disable_func'      => '',
			'cap'               => '',
			'save_sanitize'     => '',
			'theme'             => 'table',
			'fields'            => [
				'foo' => [ 'title' =>'Первое метаполе' ],
				'bar' => [ 'title' =>'Второе метаполе' ],
			],
		];

		$this->opt = (object) array_merge( $defaults, $opt );

		// хуки инициализации, вешается на хук init чтобы текущий пользователь уже был установлен
		add_action( 'init', [ $this, 'init_hooks' ], 20 );
	}

	function init_hooks(){

		// может метабокс отключен по праву
		if( $this->opt->cap && ! current_user_can( $this->opt->cap ) ){
			return;
		}

		// тема оформления
		$this->set_theme();

		// создадим уникальный ID объекта
		$_opt = (array) clone $this->opt;
		// удалим все closure
		array_walk_recursive( $_opt, static function( &$val, $key ){
			if( $val instanceof Closure ) $val = '';
		});
		$this->id = substr( md5( serialize( $_opt ) ), 0, 7 ); // ID экземпляра

		// сохраним ссылку на экземпляр, чтобы к нему был доступ
		self::$instances[ $this->opt->id ][ $this->id ] = & $this;

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
		add_action( 'save_post', [ $this, 'meta_box_save' ], 1, 2 );
	}

	function add_meta_box( $post_type, $post ){

		$opt = $this->opt;

		if(
			in_array( $post_type, [ 'comment', 'link' ], true )
			|| ! current_user_can( get_post_type_object( $post_type )->cap->edit_post, $post->ID )
			|| ( $opt->post_type_feature && ! post_type_supports( $post_type, $opt->post_type_feature ) )
			|| ( $opt->post_type_options && ! in_array( $post_type, get_post_types( $opt->post_type_options, 'names', 'or' ) ) )
			|| ( $opt->disable_func && is_callable($opt->disable_func) && call_user_func( $opt->disable_func, $post ) )
			|| in_array( $post_type, (array) $opt->not_post_type, true )
		){
			return;
		}

		$p_types = $opt->post_type ?: $post_type;

		// if WP < 4.4
		if( is_array( $p_types ) && version_compare( $GLOBALS['wp_version'], '4.4', '<' ) ){
			foreach( $p_types as $p_type ){
				add_meta_box( $this->id, $opt->title, [ $this, 'meta_box', ], $p_type, $opt->context, $opt->priority );
			}
		}
		else {
			add_meta_box( $this->id, $opt->title, [ $this, 'meta_box' ], $p_types, $opt->context, $opt->priority );
		}

		// добавим css класс к метабоксу
		// apply_filters( "postbox_classes_{$page}_{$id}", $classes );
		add_filter( "postbox_classes_{$post_type}_{$this->id}", [ $this, '_postbox_classes_add' ] );
	}

	/**
	 * Выводит HTML код блока.
	 *
	 * @param object $post Объект записи
	 */
	public function meta_box( $post ){

		$fields_out = $hidden_out = '';

		foreach( $this->opt->fields as $key => $args ){

			// пустое поле
			if( ! $key || ! $args ){
				continue;
			}

			if( empty( $args['title_patt'] ) ) $args['title_patt'] = $this->opt->title_patt ?? '%s';
			if( empty( $args['desc_patt'] )  ) $args['desc_patt']  = $this->opt->desc_patt  ?? '%s';
			if( empty( $args['field_patt'] ) ) $args['field_patt'] = $this->opt->field_patt ?? '%s';

			$args['key'] = $key;

			$field_wrap = & $this->opt->field_wrap;
			if( 'wp_editor' === ( $args['type'] ?? '' ) ){
				$field_wrap = str_replace( [ '<p ', '</p>' ], [ '<div ', '</div><br>' ], $field_wrap );
			}

			if( 'hidden' === ( $args['type'] ?? '' ) ){
				$hidden_out .= $this->field( $args, $post );
			}
			else{
				$fields_out .= sprintf( $field_wrap, $key . '_meta', $this->field( $args, $post ) );
			}

		}

		$metabox_desc = '';
		if( $this->opt->desc ){
			$metabox_desc = is_callable( $this->opt->desc )
				? call_user_func( $this->opt->desc, $post )
				: '<p class="description">' . $this->opt->desc . '</p>';
		}

		echo ( $this->opt->css ? '<style>'. $this->opt->css .'</style>' : '' ) .
		     $metabox_desc .
		     $hidden_out .
		     sprintf( ( $this->opt->fields_wrap ?: '%s' ), $fields_out ) .
		     '<div class="clearfix"></div>';
	}

	/**
	 * Сохраняем данные, при сохранении поста
	 * @param  integer $post_id ID записи
	 * @return boolean  false если проверка не пройдена
	 */
	public function meta_box_save( $post_id, $post ){

		if(	! ( $save_metadata = isset($_POST[ $key="{$this->id}_meta" ]) ? $_POST[$key] : '' )       // нет данных
		       || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE  )                                           // выходим, если это автосохр.
		       || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_'. $post_id )                          // nonce проверка
		       || ( $this->opt->post_type && ! in_array( $post->post_type, (array) $this->opt->post_type ) ) // не подходящий тип записи
		)
			return null;

		// оставим только поля текущего класса (защиты от подмены поля)
		$_key_prefix = $this->_key_prefix();
		$fields_data = array();
		foreach( $this->opt->fields as $_key => $rg ){
			$meta_key = $_key_prefix . $_key;

			// недостаточно прав
			if( !empty($rg['cap']) && ! current_user_can( $rg['cap'] ) )
				continue;

			// пропускаем отключенные поля
			if( !empty($rg['disable_func']) && is_callable($rg['disable_func']) && call_user_func( $rg['disable_func'], $post, $meta_key ) )
				continue;

			$fields_data[ $meta_key ] = $rg;
		}
		$fields_names  = array_keys( $fields_data );
		$save_metadata = array_intersect_key( $save_metadata, array_flip($fields_names) );

		// Очистка
		if( 'sanitize' ){
			// своя функция очистки
			if( is_callable($this->opt->save_sanitize) ){
				$save_metadata = call_user_func_array( $this->opt->save_sanitize, [ $save_metadata, $post_id, $fields_data ] );
				$sanitized = true;
			}
			// хук очистки
			if( has_filter("kpmb_save_sanitize_{$this->opt->id}") ){
				$save_metadata = apply_filters("kpmb_save_sanitize_{$this->opt->id}", $save_metadata, $post_id, $fields_data );
				$sanitized = true;
			}
			// если нет функции и хука очистки, то чистим все поля с помощью wp_kses() или sanitize_text_field()
			if( empty($sanitized) ){

				foreach( $save_metadata as $meta_key => & $value ){
					// есть функция очистки отдельного поля
					if( !empty($fields_data[$meta_key]['sanitize_func']) && is_callable($fields_data[$meta_key]['sanitize_func']) ){
						$value = call_user_func( $fields_data[$meta_key]['sanitize_func'], $value );
					}
					// не чистим
					elseif( 'none' === ( $fields_data[$meta_key]['sanitize_func'] ?? '' ) ){
						// skip
					}
					// не чистим - видимо это произвольная функция вывода полей, которая сохраняет массив
					elseif( is_array($value) ){}
					// нет функции очистки отдельного поля
					else {

						$type = !empty($fields_data[$meta_key]['type']) ? $fields_data[$meta_key]['type'] : 'text';

						if( $type === 'number' ){
							$value = floatval( $value );
						}
						elseif( $type === 'email' ){
							$value = sanitize_email( $value );
						}
						// wp_editor, textarea
						elseif( in_array( $type, [ 'wp_editor', 'textarea' ], true ) ){
							$value = addslashes( wp_kses( stripslashes( $value ), 'post' ) ); // default ?
						}
						// text, radio, checkbox, color, date, month, tel, time, url
						else{
							$value = sanitize_text_field( $value );
						}
					}
				}
				unset( $value );

			}
		}

		// Сохраняем
		foreach( $save_metadata as $meta_key => $value ){
			// если есть функция сохранения
			if(
				! empty( $fields_data[ $meta_key ]['update_func'] )
				&&
				is_callable( $fields_data[ $meta_key ]['update_func'] )
			){
				call_user_func( $fields_data[ $meta_key ]['update_func'], $post, $meta_key, $value );
			}
			else {
				// удаляем поле, если значение пустое. 0 остается...
				if( ! $value && ( $value !== '0' ) ){
					delete_post_meta( $post_id, $meta_key );
				}
				// add_post_meta() работает автоматически
				else{
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}

	public function _postbox_classes_add( $classes ){
		$classes[] = "kama_meta_box_{$this->opt->id}";
		return $classes;
	}

	public function _key_prefix(){
		return ( '_' === $this->opt->id[0] ) ? '' : "{$this->opt->id}_";
	}

}

trait Kama_Post_Meta_Box__Fields {

	protected $_rg;
	protected $_post;
	protected $_var;

	protected $field_pattern = '{field}{desc}';

	/**
	 * Выводит отдельные мета поля.
	 *
	 * @param string $name Атрибут name.
	 * @param array  $args Параметры поля.
	 * @param object $post Объект текущего поста.
	 *
	 * @return string|null HTML код
	 */
	protected function field( $args, $post ){

		$var = (object) []; // внутренние переменные этой фукнции, будут переданы в методы
		$rg  = (object) array_merge( [
			'type'          => '',
			'title'         => '',
			'desc'          => '',
			'placeholder'   => '',
			'id'            => '',
			'class'         => '',
			'attr'          => '',
			'val'           => '',
			'options'       => '',
			'callback'      => '',
			'sanitize_func' => '',
			'output_func'   => '',
			'update_func'   => '',
			'disable_func'  => '',
			'cap'           => '',

			// служебные
			'key'           => '', // Обязательный! Автоматический
			'title_patt'    => '', // Обязательный! Автоматический
			'field_patt'    => '', // Обязательный! Автоматический
			//'desc_patt'     => '', // Обязательный! Автоматический
		], $args );

		if( $rg->cap && ! current_user_can( $rg->cap ) ){
			return null;
		}

		if( strpos( $rg->key, 'sep_' ) === 0 ){
			$rg->type = 'sep';
		}
		if( ! $rg->type ){
			$rg->type = 'text';
		}

		$var->meta_key = $this->_key_prefix() . $rg->key;

		// поле отключено
		if(
			$rg->disable_func
			&& is_callable( $rg->disable_func )
			&& call_user_func( $rg->disable_func, $post, $var->meta_key )
		){
			return null;
		}

		// meta_val
		$rg->val = get_post_meta( $post->ID, $var->meta_key, true ) ?: $rg->val;
		if( $rg->output_func && is_callable( $rg->output_func ) ){
			$rg->val = call_user_func( $rg->output_func, $post, $var->meta_key, $rg->val );
		}

		$var->name = "{$this->id}_meta[$var->meta_key]";

		$rg->id  = $rg->id ?: "{$this->opt->id}_{$rg->key}";

		// при табличной теме, td заголовка должен выводиться всегда!
		if( false !== strpos( $rg->title_patt, '<td ' ) ){
			$var->title = sprintf( $rg->title_patt, $rg->title ) . ( $rg->title ? ' ' : '' );
		}
		else{
			$var->title = $rg->title ? sprintf( $rg->title_patt, $rg->title ) . ' ' : '';
		}

		$rg->options = (array) $rg->options;

		$var->pholder = $rg->placeholder ? ' placeholder="'. esc_attr($rg->placeholder) .'"' : '';
		$var->class = $rg->class ? ' class="'. $rg->class .'"' : '';

		$this->_rg = $rg;
		$this->_post = $post;
		$this->_var = $var;

		// произвольная функция
		if( is_callable( $rg->callback ) ){
			$out = $var->title . $this->fn__field( call_user_func( $rg->callback, $args, $post, $var->name, $rg->val, $rg, $var ) );
		}
		// произвольный метод
		// вызов метода `$this->field__{FIELD}()` (для возможности расширить этот класс)
		elseif( method_exists( $this, "field__$rg->type" ) ){
			$out = $this->{"field__$rg->type"}( $rg, $var, $post );
		}
		// text, email, number, url, tel, color, password, date, month, week, range
		else{
			$out = $this->field__default( $rg, $var, $post );
		}

		return $out;
	}

	protected function fn__desc(): string {

		[ $rg, $post, $var ] = [ $this->_rg, $this->_post, $this->_var ];

		if( ! $rg->desc ){
			return '';
		}

		$desc = is_callable( $rg->desc )
			? call_user_func( $rg->desc, $post, $var->meta_key, $rg->val, $var->name )
			: $rg->desc;

		return sprintf( $this->opt->desc_patt, $desc );
	}

	protected function fn__field( $field ){
		$rg = $this->_rg;

		return sprintf( $rg->field_patt, $field );
	}

	protected function field_desc_concat( $field, $desc ){

		// description before field
		if( false === strpos( $this->opt->desc_patt, '<br' ) ){
			return $desc . $field;
		}

		// descroption after field
		return $field . $desc;
	}

	// sep
	protected function field__sep( $rg, $var, $post ){

		$_style = 'font-weight:600; ';
		if( preg_match( '/style="([^"]+)"/', $rg->attr, $mm ) ){
			$_style .= $mm[1];
		}

		if( false !== strpos( $rg->field_patt, '<td' ) ){
			return str_replace( '<td ', '<td class="kpmb__sep" colspan="2" style="padding:1em .5em; ' . $_style . '"', $this->fn__field( $rg->title ) );
		}

		return '<span class="kpmb__sep" style="display:block; padding:1em; font-size:110%; '. $_style .'">'. $rg->title .'</span>';
	}

	// textarea
	protected function field__textarea( $rg, $var, $post ){
		$_style = (false === strpos($rg->attr,'style=')) ? ' style="width:98%;"' : '';

		$field = sprintf( '<textarea %s id="%s" name="%s">%s</textarea>',
			( $rg->attr . $var->class . $var->pholder . $_style ),
			$rg->id,
			$var->name,
			esc_textarea( $rg->val )
		);

		return $var->title . $this->fn__field( $this->field_desc_concat( $field, $this->fn__desc() ) );
	}

	// select
	protected function field__select( $rg, $var, $post ){

		$is_assoc = ( array_keys($rg->options) !== range(0, count($rg->options) - 1) ); // ассоциативный или нет?
		$_options = array();
		foreach( $rg->options as $v => $l ){
			$_val       = $is_assoc ? $v : $l;
			$_options[] = '<option value="'. esc_attr($_val) .'" '. selected($rg->val, $_val, 0) .'>'. $l .'</option>';
		}

		$field = sprintf( '<select %s id="%s" name="%s">%s</select>',
			( $rg->attr . $var->class ),
			$rg->id,
			$var->name,
			implode("\n", $_options )
		);

		return $var->title . $this->fn__field( $this->field_desc_concat( $field, $this->fn__desc() ) );
	}

	// radio
	protected function field__radio( $rg, $var, $post ){

		$radios = array();

		foreach( $rg->options as $v => $l ){
			$radios[] = '
			<label '. $rg->attr . $var->class .'>
				<input type="radio" name="'. $var->name .'" value="'. $v .'" '. checked($rg->val, $v, 0) .'>'. $l .'
			</label> ';
		}

		$field = '<span class="radios">'. implode("\n", $radios ) .'</span>';

		return $var->title . $this->fn__field( $this->field_desc_concat( $field, $this->fn__desc() ) );
	}

	// checkbox
	protected function field__checkbox( $rg, $var, $post ){

		$patt = '
		<label {attrs}>
			<input type="hidden" name="{name}" value="">
			<input type="checkbox" id="{id}" name="{name}" value="{value}" {checked}>
			{desc}
		</label>
		';

		$field = strtr( $patt, [
			'{attrs}'     => $rg->attr . $var->class,
			'{name}'      => $var->name,
			'{id}'        => $rg->id,
			'{value}'     => esc_attr(reset($rg->options) ?: 1),
			'{checked}'   => checked( $rg->val, (reset($rg->options) ?: 1), 0),
			'{desc}'      => $rg->desc ?: '',
		] );

		return $var->title . $this->fn__field( $field );
	}

	// hidden
	protected function field__hidden( $rg, $var, $post ){

		return sprintf( '<input type="%s" id="%s" name="%s" value="%s" title="%s">',
			$rg->type,
			$rg->id,
			$var->name,
			esc_attr( $rg->val ),
			esc_attr( $rg->title )
		);
	}

	// text, email, number, url, tel, color, password, date, month, week, range
	protected function field__default( $rg, $var, $post ){

		$_style = ( $rg->type === 'text' && false === strpos($rg->attr, 'style=') ) ? ' style="width:100%;"' : '';

		$field = sprintf( '<input %s type="%s" id="%s" name="%s" value="%s">',
			( $rg->attr . $var->class  . $var->pholder . $_style ),
			$rg->type,
			$rg->id,
			$var->name,
			esc_attr( $rg->val ),
			esc_attr( $rg->title )
		);

		return $var->title . $this->fn__field( $this->field_desc_concat( $field, $this->fn__desc() ) );
	}

	// wp_editor
	protected function field__wp_editor( $rg, $var, $post ){

		$ed_args = array_merge( [
			'textarea_name'    => $var->name, //нужно указывать!
			'editor_class'     => $rg->class,
			// изменяемое
			'wpautop'          => 1,
			'textarea_rows'    => 5,
			'tabindex'         => null,
			'editor_css'       => '',
			'teeny'            => 0,
			'dfw'              => 0,
			'tinymce'          => 1,
			'quicktags'        => 1,
			'media_buttons'    => false,
			'drag_drop_upload' => false,
		], $rg->options );

		ob_start();
		wp_editor( $rg->val, $rg->id, $ed_args );
		$field = ob_get_clean();

		return $var->title . $this->fn__field( $this->field_desc_concat( $field, $this->fn__desc() ) );
	}

	// image
	protected function field__image( $rg, $var, $post ){

		wp_enqueue_media();

		static $once;
		if( ! $once && $once = 1 ){
			add_action( 'admin_print_footer_scripts', function(){
				?>
				<script>
					jQuery('.kmb_img_wrap').each(function(){

						let $ = jQuery

						let frame
						let $wrap  = $(this)
						let $img   = $wrap.find('img')
						let $input = $wrap.find('input[type="hidden"]')

						$wrap.on( 'click', '.set_img', function(){

							let post_id = $(this).data('post_id') || null

							//if( frame && frame.post_id === post_id ){
							//	frame.open();
							//	return;
							//}

							frame = wp.media.frames.kmbframe = wp.media({
								title   : '<?= __( 'Add Media' ) ?>',
								// Library WordPress query arguments.
								library : {
									type       : 'image',
									uploadedTo : post_id
								},
								multiple: false,
								button: {
									text: '<?= __( 'Apply' ) ?>'
								}
							});

							frame.on( 'select', function() {
								attachment = frame.state().get('selection').first().toJSON();
								$img.attr( 'src', attachment.url );

								$wrap.data('usetype') === 'url' ? $input.val( attachment.url ) : $input.val( attachment.id );
							});

							frame.on( 'open', function(){
								if( $input.val() )
									frame.state().get('selection').add( wp.media.attachment( $input.val() ) );
							});

							frame.open();
							//frame.post_id = post_id // save
						});

						$wrap.on( 'click', '.del_img', function(){
							$img.attr( 'src', '' );
							$input.val('');
						});
					})
				</script>
				<?php
			}, 99 );
		}

		$usetype = $rg->options ? $rg->options[0] : 'id'; // может быть: id, url

		if( ! $src = is_numeric( $rg->val ) ? wp_get_attachment_url( $rg->val ) : $rg->val ){
			$src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
		}

		ob_start();
		?>
		<span class="kmb_img_wrap" data-usetype="<?= esc_attr($usetype) ?>" style="display:flex; align-items:center;">
			<img src="<?= esc_url($src) ?>" style="max-height:100px; max-width:100px; margin-right:1em;" alt="">
			<span>
				<input class="set_img button button-small" type="button" data-post_id="<?= $post->ID ?>" value="<?= __( 'Images' ) .' '. __( 'Post' ) ?>" />
				<input class="set_img button button-small" type="button" value="<?= __('Set image') ?>" />
				<input class="del_img button button-small" type="button" value="<?= __('Remove')?>" />

				<input type="hidden" name="<?= $var->name ?>" value="<?= esc_attr($rg->val) ?>">
			</span>
		</span>
		<?php
		$field = ob_get_clean();

		return $var->title . $this->fn__field( $field );
	}

}

trait Kama_Post_Meta_Box__Themes {

	private function set_theme(): void {

		$opt_theme = & $this->opt->theme;

		$def_opt_theme = [
			'line' => [
				// CSS стили всего блока. Например: '.postbox .tit{ font-weight:bold; }'
				'css'         => '',
				// '%s' будет заменено на html всех полей
				'fields_wrap' => '%s',
				// '%2$s' будет заменено на html поля (вместе с заголовком, полем и описанием)
				'field_wrap'  => '<p class="%1$s">%2$s</p>',
				// '%s' будет заменено на заголовок
				'title_patt'  => '<strong class="tit"><label>%s</label></strong>',
				// '%s' будет заменено на HTML поля (вместе с описанием)
				'field_patt'  => '%s',
				// '%s' будет заменено на текст описания
				'desc_patt'   => '<p class="description" style="opacity:0.6;">%s</p>',
			],
			'table' => [
				'css'         => '.kpmb-table td{ padding:.6em .5em; } .kpmb-table tr:hover{ background:rgba(0,0,0,.03); }',
				'fields_wrap' => '<table class="form-table kpmb-table">%s</table>',
				'field_wrap'  => '<tr class="%1$s">%2$s</tr>',
				'title_patt'  => '<td style="width:10em;" class="tit">%s</td>',
				'field_patt'  => '<td class="field">%s</td>',
				'desc_patt'   => '<p class="description" style="opacity:0.8;">%s</p>',
			],
			'grid' => [
				'css'         => '
					.kpmb-grid{ margin:-6px -12px -12px }
					.kpmb-grid__item{ display:grid; grid-template-columns:15em 2fr; grid-template-rows:1fr; border-bottom:1px solid rgba(0,0,0,.1) }
					.kpmb-grid__item:last-child{ border-bottom:none }
					.kpmb-grid__title{ padding:1.5em; background:#F9F9F9; border-right:1px solid rgba(0,0,0,.1); font-weight:600 }
					.kpmb-grid__field{ align-self:center; padding:1em 1.5em }
					.kpmb__sep{ grid-column: 1 / span 2; }
				',
				'fields_wrap' => '<div class="kpmb-grid">%s</div>',
				'field_wrap'  => '<div class="kpmb-grid__item %1$s">%2$s</div>',
				'title_patt'  => '<div class="kpmb-grid__title">%s</div>',
				'field_patt'  => '<div class="kpmb-grid__field">%s</div>',
				'desc_patt'   => '<p class="description" style="opacity:0.8;">%s</p>',
			],
		];

		if( is_string( $opt_theme ) ){
			$def_opt_theme = $def_opt_theme[ $opt_theme ];
		}
		// позволяет изменить отдельные поля темы оформелния метабокса
		else {
			$opt_theme_key = key( $opt_theme ); // индекс массива

			// в индексе указана тема: [ 'table' => [ 'desc_patt' => '<div>%s</div>' ] ]
			if( isset( $def_opt_theme[ $opt_theme_key ] ) ){
				$def_opt_theme = $def_opt_theme[ $opt_theme_key ]; // основа
				$opt_theme     = $opt_theme[ $opt_theme_key ];
			}
			// в индексе указана не тема: [ 'desc_patt' => '<div>%s</div>' ]
			else {
				$def_opt_theme = $def_opt_theme['line']; // основа
			}
		}

		$opt_theme = is_array( $opt_theme ) ? array_merge( $def_opt_theme, $opt_theme ) : $def_opt_theme;

		if( 'after' === $this->opt->fields_desc_pos ){
			$opt_theme['desc_patt'] = '<br>'. $opt_theme['desc_patt'];
		}

		// позволяет изменить тему
		$opt_theme = apply_filters( 'kp_metabox_theme', $opt_theme, $this->opt );

		// Переменные theme в общие параметры.
		// Если в параметрах уже есть переменная, то она остается как есть (это позволяет изменить отдельный элемент темы).
		foreach( $opt_theme as $kk => $vv ){
			if( ! isset( $this->opt->$kk ) ){
				$this->opt->$kk = $vv;
			}
		}

	}
}



