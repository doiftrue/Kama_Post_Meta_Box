<?php

if( class_exists('Kama_Post_Meta_Box') ){
	return;
}

/**
 * Creates a block of custom fields for the specified post types.
 *
 * Possible parameters of the class, see: `Kama_Post_Meta_Box::__construct()`.
 * Possible parameters for each field, see in: `Kama_Post_Meta_Box::field()`.
 *
 * When saved, clears each field, via: `wp_kses()` or sanitize_text_field().
 * The sanitizing function can be replaced via a hook `kpmb_save_sanitize_{id}`.
 * And You can also specify the name of the sanitizing function in the `save_sanitize` parameter.
 * If you specify a sanitizing function in both a parameter and a hook, then both will work!
 * Both sanitizing functions gets two parameters: `$metas` (all meta-fields), `$post_id`.
 *
 * The block is rendered and the meta-fields are saved for users with edit current post capability only.
 *
 * PHP: 7+
 *
 * @changlog https://github.com/doiftrue/Kama_Post_Meta_Box/blob/master/changelog.md
 *
 * @version 1.11.2
 */
class Kama_Post_Meta_Box {

	use Kama_Post_Meta_Box__Fields_Part;
	use Kama_Post_Meta_Box__Themes;

	public $opt;

	public $id;

	static $instances = array();

	/**
	 * @var Kama_Post_Meta_Box_Fields
	 */
	protected $fields;

	const METABOX_ARGS = [
		'id'                => '',
		'title'             => '',
		'desc'              => '',
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
			'foo' => [ 'title' => 'First meta-field' ],
			'bar' => [ 'title' => 'Second meta-field' ],
		],
	];

	const FIELD_ARGS =  [
		'type'          => '',
		'title'         => '',
		'desc'          => '',
		'desc_before'   => '',
		'desc_after'    => '',
		'placeholder'   => '',
		'id'            => '',
		'class'         => '',
		'attr'          => '',
		'val'           => '',
		'options'       => '',
		'params'        => [], // additional field options
		'callback'      => '',
		'sanitize_func' => '',
		'output_func'   => '',
		'update_func'   => '',
		'disable_func'  => '',
		'cap'           => '',

		// служебные
		'key'           => '', // Mandatory! Automatic
		'title_patt'    => '', // Mandatory! Automatic
		'field_patt'    => '', // Mandatory! Automatic
	];

	/**
	 * Конструктор.
	 *
	 * @param array           $opt             {
	 *     Опции по которым будет строиться метаблок.
	 *
	 *     @type string          $id                 Иднетификатор блока. Используется как префикс для названия метаполя.
	 *                                               Начните с '_' >>> '_foo', чтобы ID не был префиксом в названии метаполей.
	 *     @type string          $title              Заголовок блока.
	 *     @type string|callback $desc               Описание для метабокса (сразу под заголовком). Коллбэк получит $post.
	 *     @type string|array    $post_type          Типы записей для которых добавляется блок:
	 *                                               `[ 'post', 'page' ]`. По умолчанию: `''` = для всех типов записей.
	 *     @type string|array    $not_post_type      Типы записей для которых метабокс не должен отображаться.
	 *     @type string          $post_type_feature  Строка. Возможность которая должна быть у типа записи,
	 *                                               чтобы метабокс отобразился. See https://wp-kama.ru/post_type_supports
	 *     @type string          $post_type_options  Массив. Опции типа записи, которые должны быть у типа записи,
	 *                                               чтобы метабокс отобразился. See перывый параметр https://wp-kama.ru/get_post_types
	 *     @type string          $priority           Приоритет блока для показа выше или ниже остальных блоков ('high' или 'low').
	 *     @type string          $context            Место где должен показываться блок ('normal', 'advanced' или 'side').
	 *     @type callback        $disable_func       Функция отключения метабокса во время вызова самого метабокса.
	 *                                               Если вернет что-либо кроме false/null/0/array(), то метабокс будет отключен.
	 *                                               Передает объект поста.
	 *     @type string          $cap                Название права пользователя, чтобы показывать метабокс.
	 *     @type callback        $save_sanitize      Функция очистки сохраняемых в БД полей. Получает 2 параметра:
	 *                                               $metas - все поля для очистки и $post_id.
	 *     @type string          $theme              Тема оформления: `table`, `line`, `grid`.
	 *                                               ИЛИ массив паттернов полей:
	 *                                               css, fields_wrap, field_wrap, title_patt, field_patt, desc_before_patt.
	 *                                               ЕСЛИ Массив указывается так: `[ 'desc_before_patt' => '<div>%s</div>' ]`
	 *                                               (за овнову будет взята тема line).
	 *                                               ЕСЛИ Массив указывается так:
	 *                                               `[ 'table' => [ 'desc_before_patt' => '<div>%s</div>' ] ]`
	 *                                               (за овнову будет взята тема table).
	 *                                               ИЛИ изменить тему можно через фильтр 'kp_metabox_theme'
	 *                                               (удобен для общего изменения темы для всех метабоксов).
	 *     @type array           $fields {
	 *         Метаполя. Собственно, сами метаполя. Список возможных ключей массива для каждого поля.
	 *
	 *         @type string $type                 Тип поля: textarea, select, checkbox, radio, image, wp_editor, hidden, sep_*.
	 *                                            Или базовые: text, email, number, url, tel, color, password, date, month, week, range.
	 *                                            'sep' - визуальный разделитель, для него нужно указать `title` и можно
	 *                                            указать `'attr'=>'style="свои стили"'`.
	 *                                            'sep' - чтобы удобнее указывать тип 'sep' начните ключ поля с
	 *                                            `sep_`: 'sep_1' => [ 'title'=>'Разделитель' ].
	 *                                            Для типа `image` можно указать тип сохраняемого значения в
	 *                                            `options`: 'options'=>'url'. По умолчанию тип = id.
	 *                                            По умолчанию 'text'.
	 *         @type string $title                Заголовок метаполя.
	 *         @type string|callback $desc        Описание для поля. Можно указать функцию/замыкание, она получит параметры:
	 *                                            $post, $meta_key, $val, $name.
	 *         @type string|callback $desc_before Алиас $desc.
	 *         @type string|callback $desc_after  Тоже что $desc, только будет выводиться внизу поля.
	 *         @type string $placeholder          Атрибут placeholder.
	 *         @type string $id                   Атрибут id. По умолчанию: $this->opt->id .'_'. $key.
	 *         @type string $class                Атрибут class: добавляется в input, textarea, select.
	 *                                            Для checkbox, radio в оборачивающий label.
	 *         @type string $attr                 Любая строка. Атрибуты HTML тега элемента формы (input).
	 *         @type string $wrap_attr            Любая строка. Атрибуты HTML тега оборачивающего поле: `style="width:50%;"`.
	 *         @type string $val                  Значение по умолчанию, если нет сохраненного.
	 *         @type string $options              массив: array('значение'=>'название') - варианты для типов 'select', 'radio'.
	 *                                            Для 'wp_editor' стенет аргументами.
	 *                                            Для 'checkbox' станет значением атрибута value:
	 *                                            <input type="checkbox" value="{options}">.
	 *                                            Для 'image' определяет тип сохраняемого в метаполе значения:
	 *                                            id (ID вложения), url (url вложения).
	 *         @type callback $callback           Название функции, которая отвечает за вывод поля.
	 *                                            Если указана, то ни один параметр не учитывается и за вывод
	 *                                            полностью отвечает указанная функция.
	 *                                            Получит параметры: $args, $post, $name, $val, $rg, $var
	 *         @type callback $sanitize_func      Функция очистки данных при сохранении - название функции или Closure.
	 *                                            Укажите 'none', чтобы не очищать данные...
	 *                                            Работает, только если не установлен глобальный параметр 'save_sanitize'...
	 *                                            Получит параметр $value - сохраняемое значение поля.
	 *         @type callback $output_func        Функция обработки значения, перед выводом в поле.
	 *                                            Получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.
	 *         @type callback $update_func        Функция сохранения значения в метаполя.
	 *                                            Получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.
	 *         @type callback $disable_func       Функция отключения поля.
	 *                                            Если не false/null/0/array() - что-либо вернет, то поле не будет выведено.
	 *                                            Получает парамтры: $post, $meta_key
	 *         @type string $cap                  Название права пользователя, чтобы видеть и изменять поле.
	 *     }
	 *
	 * }
	 */
	public function __construct( array $opt ){

		$this->opt = (object) array_merge( self::METABOX_ARGS, $opt );

		$fields_class = apply_filters( 'kama_post_meta_box__fields_class', 'Kama_Post_Meta_Box_Fields' );
		$this->fields = new $fields_class( $this );

		// хуки инициализации, вешается на хук init чтобы текущий пользователь уже был установлен
		add_action( 'init', [ $this, 'init_hooks' ], 20 );
	}

	public function init_hooks(): void {

		// maybe the metabox is disabled by capability.
		if( $this->opt->cap && ! current_user_can( $this->opt->cap ) ){
			return;
		}

		// design theme.
		$this->set_theme();

		// create a unique object ID.
		$_opt = (array) clone $this->opt;
		// delete all closures.
		array_walk_recursive( $_opt, static function( &$val, $key ){
			( $val instanceof Closure ) && $val = '';
		});
		$this->id = substr( md5( serialize( $_opt ) ), 0, 7 ); // ID экземпляра

		// keep a reference to the instance so that it can be accessed.
		self::$instances[ $this->opt->id ][ $this->id ] = & $this;

		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
		add_action( 'save_post', [ $this, 'meta_box_save' ], 1, 2 );
	}

	public function add_meta_box( $post_type, $post ): void {

		$opt = $this->opt;

		/** @noinspection NotOptimalIfConditionsInspection */
		if(
			in_array( $post_type, [ 'comment', 'link' ], true )
			|| ! current_user_can( get_post_type_object( $post_type )->cap->edit_post, $post->ID )
			|| ( $opt->post_type_feature && ! post_type_supports( $post_type, $opt->post_type_feature ) )
			|| ( $opt->post_type_options && ! in_array( $post_type, get_post_types( $opt->post_type_options, 'names', 'or' ), true ) )
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
	 * Outputs the HTML code of the block.
	 *
	 * @param object $post Post object.
	 */
	public function meta_box( $post ){

		$fields_out = $hidden_out = '';

		foreach( $this->opt->fields as $key => $args ){

			// пустое поле
			if( ! $key || ! $args ){
				continue;
			}

			empty( $args['title_patt'] ) && $args['title_patt'] = $this->opt->title_patt ?? '%s';
			empty( $args['desc_before_patt'] )  && $args['desc_before_patt']  = $this->opt->desc_before_patt  ?? '%s';
			empty( $args['field_patt'] ) && $args['field_patt'] = $this->opt->field_patt ?? '%s';

			$args['key'] = $key;

			$field_wrap = & $this->opt->field_wrap;
			if( 'wp_editor' === ( $args['type'] ?? '' ) ){
				$field_wrap = str_replace( [ '<p ', '</p>' ], [ '<div ', '</div><br>' ], $field_wrap );
			}

			if( 'hidden' === ( $args['type'] ?? '' ) ){
				$hidden_out .= $this->field( $args, $post );
			}
			else{
				$fields_out .= sprintf( $field_wrap, "{$key}_meta", $this->field( $args, $post ), ( $args['wrap_attr'] ?? '' ) );
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
	 * Saving data, when saving a post.
	 *
	 * @param int     $post_id Record ID.
	 * @param WP_Post $post
	 *
	 * @return void|null False If the check is not passed.
	 */
	public function meta_box_save( $post_id, $post ): void {

		if(
			// no data
			! ( $save_metadata = isset( $_POST[ $key = "{$this->id}_meta" ] ) ? $_POST[ $key ] : '' )
			// Exit, if it is autosave.
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			// nonce check
			|| ! wp_verify_nonce( $_POST['_wpnonce'], "update-post_$post_id" )
			// unsuitable post type
			|| ( $this->opt->post_type && ! in_array( $post->post_type, (array) $this->opt->post_type, true ) )
		){
			return;
		}

		// leave only the fields of the current class (protection against field swapping)
		$_key_prefix = $this->_key_prefix();
		$fields_data = array();
		foreach( $this->opt->fields as $_key => $rg ){
			$meta_key = $_key_prefix . $_key;

			// not enough rights
			if( ! empty( $rg['cap'] ) && ! current_user_can( $rg['cap'] ) ){
				continue;
			}

			// Skip the disabled fields
			if(
				! empty( $rg['disable_func'] )
				&& is_callable( $rg['disable_func'] )
				&& call_user_func( $rg['disable_func'], $post, $meta_key )
			){
				continue;
			}

			$fields_data[ $meta_key ] = $rg;
		}
		$fields_names  = array_keys( $fields_data );
		$save_metadata = array_intersect_key( $save_metadata, array_flip( $fields_names ) );

		// Sanitizing
		if( 'sanitize' ){

			// Own sanitizing.
			if( is_callable( $this->opt->save_sanitize ) ){
				$save_metadata = call_user_func( $this->opt->save_sanitize, $save_metadata, $post_id, $fields_data );
				$sanitized = true;
			}

			// Sanitizing hook.
			if( has_filter( "kpmb_save_sanitize_{$this->opt->id}" ) ){
				$save_metadata = apply_filters( "kpmb_save_sanitize_{$this->opt->id}", $save_metadata, $post_id, $fields_data );
				$sanitized = true;
			}

			// If there is no sanitizing function or hook, then clean all fields with wp_kses() or sanitize_text_field().
			if( empty( $sanitized ) ){

				foreach( $save_metadata as $meta_key => & $value ){

					// there is a function for cleaning a separate field
					if(
						! empty( $fields_data[ $meta_key ]['sanitize_func'] )
						&&
						is_callable( $fields_data[ $meta_key ]['sanitize_func'] )
					){
						$value = call_user_func( $fields_data[ $meta_key ]['sanitize_func'], $value );
					}
					// do not clean
					elseif( 'none' === ( $fields_data[$meta_key]['sanitize_func'] ?? '' ) ){
						// skip
					}
					// do not clean - apparently it is an arbitrary field output function that saves an array
					elseif( is_array( $value ) ){
						// skip
					}
					// there is no function for cleaning an individual field
					else {

						$type = !empty($fields_data[$meta_key]['type']) ? $fields_data[$meta_key]['type'] : 'text';

						if( $type === 'number' ){
							$value = (float) $value;
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

		// Save
		foreach( $save_metadata as $meta_key => $value ){
			// If there is a save function
			if(
				! empty( $fields_data[ $meta_key ]['update_func'] )
				&&
				is_callable( $fields_data[ $meta_key ]['update_func'] )
			){
				call_user_func( $fields_data[ $meta_key ]['update_func'], $post, $meta_key, $value );
			}
			elseif( ! $value && ( $value !== '0' ) ){
				delete_post_meta( $post_id, $meta_key );
			}
			// add_post_meta() works automatically
			else{
				update_post_meta( $post_id, $meta_key, $value );
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

trait Kama_Post_Meta_Box__Fields_Part {

	protected $_rg;
	protected $_post;
	protected $_var;

	/**
	 * Outputs individual meta field.
	 *
	 * @param string $name The name attribute.
	 * @param array  $args Field parameters.
	 * @param object $post The object of the current post.
	 *
	 * @return string|null HTML code
	 */
	protected function field( $args, $post ){

		// internal variables of this function, will be transferred to the methods
		$var = (object) [];

		$rg = (object) array_merge( self::FIELD_ARGS, $args );

		if( $rg->cap && ! current_user_can( $rg->cap ) ){
			return null;
		}

		if( strpos( $rg->key, 'sep_' ) === 0 ){
			$rg->type = 'sep';
		}
		if( ! $rg->type ){
			$rg->type = 'text';
		}

		// standartize desc
		if( $rg->desc ){
			$rg->desc_before = $rg->desc;
		}
		if( ! $rg->desc && ! $rg->desc_before && $rg->desc_after ){
			$rg->desc = $rg->desc_after;
		}

		$var->meta_key = $this->_key_prefix() . $rg->key;

		// the field is disabled
		if(
			$rg->disable_func
			&& is_callable( $rg->disable_func )
			&& call_user_func( $rg->disable_func, $post, $var->meta_key )
		){
			return null;
		}

		// meta_val
		$var->val = get_post_meta( $post->ID, $var->meta_key, true ) ?: $rg->val;
		if( $rg->output_func && is_callable( $rg->output_func ) ){
			$var->val = call_user_func( $rg->output_func, $post, $var->meta_key, $var->val );
		}

		$var->name = "{$this->id}_meta[$var->meta_key]";

		$rg->id = $rg->id ?: "{$this->opt->id}_{$rg->key}";

		// with a table theme, the td header should always be output!
		if( false !== strpos( $rg->title_patt, '<td ' ) ){
			$var->title = sprintf( $rg->title_patt, $rg->title ) . ( $rg->title ? ' ' : '' );
		}
		else{
			$var->title = $rg->title ? sprintf( $rg->title_patt, $rg->title ) . ' ' : '';
		}

		$rg->options = (array) $rg->options;

		$var->pholder = $rg->placeholder ? ' placeholder="'. esc_attr( $rg->placeholder ) .'"' : '';
		$var->class = $rg->class ? ' class="'. $rg->class .'"' : '';

		$this->_rg = $rg;
		$this->_post = $post;
		$this->_var = $var;

		// arbitrary function
		if( is_callable( $rg->callback ) ){
			$out = $var->title . $this->tpl__field(
					call_user_func( $rg->callback, $args, $post, $var->name, $var->val, $rg, $var )
				);
		}
		// arbitrary method
		// Call the method `$this->field__{FIELD}()` (to be able to extend this class)
		elseif( method_exists( $this->fields, $rg->type ) ){
			$out = $this->fields->{ $rg->type }( $rg, $var, $post, $args );
		}
		// text, email, number, url, tel, color, password, date, month, week, range
		else{
			$out = $this->fields->default( $rg, $var, $post );
		}

		return $out;
	}

	public function tpl__field( $field ){
		$rg = $this->_rg;

		return sprintf( $rg->field_patt, $field );
	}

	public function field_desc_concat( $field ){

		[ $rg, $var, $post, $opt ] = [ $this->_rg, $this->_var, $this->_post, $this->opt ];

		$desc_fn = static function( $desc ) use( $var, $post ){

			return is_callable( $desc )
				? $desc( $post, $var->meta_key, $var->val, $var->name )
				: $desc;
		};

		// description before field
		if( $rg->desc_before ){
			$desc = sprintf( $opt->desc_before_patt, $desc_fn( $rg->desc_before ) );

			return $desc . $field;
		}

		// descroption after field
		if( $rg->desc_after ){
			$desc = sprintf( $opt->desc_after_patt, $desc_fn( $rg->desc_after ) );

			return $field . $desc;
		}

		return $field;
	}
}

trait Kama_Post_Meta_Box__Themes {

	private function set_theme(): void {

		$opt_theme = & $this->opt->theme;

		$def_opt_theme = [
			'line' => [
				// CSS styles of the whole block. For example: '.postbox .tit{ font-weight:bold; }'
				'css'         => '
					.kpmb{ display: flex; flex-wrap: wrap; justify-content: space-between; }
					.kpmb > * { width:100%; }
				    .kpmb__field{ box-sizing:border-box; margin-bottom:1em; }
				    .kpmb__tit{ display: block; margin:1em 0 .5em; font-size:115%; }
				    .kpmb__desc{ opacity:0.6; }
				    .kpmb__desc.--after{ margin-top:.5em; }
				    .kpmb__sep{ display:block; padding:1em; font-size:110%; font-weight:600; }
				    .kpmb__sep.--hr{ padding: 0; height: 1px; background: #eee; margin: 1em -12px 0 -12px; }
			    ',
				// '%s' will be replaced by the html of all fields
				'fields_wrap' => '<div class="kpmb">%s</div>',
				// '%2$s' will be replaced by field HTML (along with title, field and description)
				'field_wrap'  => '<div class="kpmb__field %1$s" %3$s>%2$s</div>',
				// '%s' will be replaced by the header
				'title_patt'  => '<strong class="kpmb__tit"><label>%s</label></strong>',
				// '%s' will be replaced by field HTML (along with description)
				'field_patt'  => '%s',
				// '%s' will be replaced by the description text
				'desc_before_patt' => '<p class="description kpmb__desc --before">%s</p>',
				'desc_after_patt'  => '<p class="description kpmb__desc --after">%s</p>',
			],
			'table' => [
				'css'         => '
					.kpmb-table td{ padding:.6em .5em; } 
					.kpmb-table tr:hover{ background:rgba(0,0,0,.03); }
					.kpmb__sep{ padding:1em .5em; font-weight:600; }
					.kpmb__desc{ opacity:0.8; }
				',
				'fields_wrap' => '<table class="form-table kpmb-table">%s</table>',
				'field_wrap'  => '<tr class="%1$s">%2$s</tr>',
				'title_patt'  => '<td style="width:10em;" class="tit">%s</td>',
				'field_patt'  => '<td class="field">%s</td>',
				'desc_before_patt' => '<p class="description kpmb__desc --before">%s</p>',
				'desc_after_patt'  => '<p class="description kpmb__desc --after">%s</p>',
			],
			'grid' => [
				'css'         => '
					.kpmb-grid{ margin:-6px -12px -12px }
					.kpmb-grid__item{ display:grid; grid-template-columns:15em 2fr; grid-template-rows:1fr; border-bottom:1px solid rgba(0,0,0,.1) }
					.kpmb-grid__item:last-child{ border-bottom:none }
					.kpmb-grid__title{ padding:1.5em; background:#F9F9F9; border-right:1px solid rgba(0,0,0,.1); font-weight:600 }
					.kpmb-grid__field{ align-self:center; padding:1em 1.5em }
					.kpmb__sep{ grid-column: 1 / span 2; display:block; padding:1em; font-size:110%; font-weight:600; }
					.kpmb__desc{ opacity:0.8; }
				',
				'fields_wrap' => '<div class="kpmb-grid">%s</div>',
				'field_wrap'  => '<div class="kpmb-grid__item %1$s">%2$s</div>',
				'title_patt'  => '<div class="kpmb-grid__title">%s</div>',
				'field_patt'  => '<div class="kpmb-grid__field">%s</div>',
				'desc_before_patt' => '<p class="description kpmb__desc --before">%s</p>',
				'desc_after_patt'  => '<br><p class="description kpmb__desc --after">%s</p>',
			],
		];

		if( is_string( $opt_theme ) ){
			$def_opt_theme = $def_opt_theme[ $opt_theme ];
		}
		// allows you to change individual fields of the metabox theme
		else {
			$opt_theme_key = key( $opt_theme ); // индекс массива

			// в индексе указана тема: [ 'table' => [ 'desc_before_patt' => '<div>%s</div>' ] ]
			if( isset( $def_opt_theme[ $opt_theme_key ] ) ){
				$def_opt_theme = $def_opt_theme[ $opt_theme_key ]; // основа
				$opt_theme     = $opt_theme[ $opt_theme_key ];
			}
			// в индексе указана не тема: [ 'desc_before_patt' => '<div>%s</div>' ]
			else {
				$def_opt_theme = $def_opt_theme['line']; // основа
			}
		}

		$opt_theme = is_array( $opt_theme ) ? array_merge( $def_opt_theme, $opt_theme ) : $def_opt_theme;

		// allows you to change the theme
		$opt_theme = apply_filters( 'kp_metabox_theme', $opt_theme, $this->opt );

		// Theme variables to global parameters.
		// If there is already a variable in the parameters, it stays as is
		// (this allows to change an individual theme element).
		foreach( $opt_theme as $kk => $vv ){
			if( ! isset( $this->opt->$kk ) ){
				$this->opt->$kk = $vv;
			}
		}

	}
}

/**
 * Separate class which contains fields.
 *
 * @method field_desc_concat( $field ) See: Kama_Post_Meta_Box__Fields_Part::field_desc_concat()
 * @method tpl__field( $field )        See: Kama_Post_Meta_Box__Fields_Part::tpl__field()
 *
 * You can add your own fields by extend this class like so:
 *
 *     add_action( 'kama_post_meta_box__fields_class', function(){
 *         return 'MY_Post_Meta_Box_Fields';
 *     } );
 *
 *     class MY_Post_Meta_Box_Fields extends Kama_Post_Meta_Box_Fields {
 *
 *         // create custom field `my_field`
 *         public function my_field( $rg, $var, $post ){
 *
 *             $field = sprintf( '<input %s type="%s" id="%s" name="%s" value="%s" title="%s">',
 *                 ( $rg->attr . $var->class  . $var->pholder ),
 *                 $rg->type,
 *                 $rg->id,
 *                 $var->name,
 *                 esc_attr( $var->val ),
 *                 esc_attr( $rg->title )
 *             );
 *
 *             return $var->title . $this->tpl__field( $this->field_desc_concat( $field ) );
 *         }
 *
 *         // override default text field
 *         public function text( $rg, $var, $post ){
 *
 *             $field = sprintf( '<input %s type="%s" id="%s" name="%s" value="%s" title="%s">',
 *                 ( $rg->attr . $var->class  . $var->pholder ),
 *                 $rg->type,
 *                 $rg->id,
 *                 $var->name,
 *                 esc_attr( $var->val ),
 *                 esc_attr( $rg->title )
 *             );
 *
 *             return $var->title . $this->tpl__field(
 *                 $this->field_desc_concat( $field )
 *             );
 *         }
 *
 *     }
 */
class Kama_Post_Meta_Box_Fields {

	/** @var Kama_Post_Meta_Box  */
	protected $kpmb;

	public function __construct( Kama_Post_Meta_Box $kpmb ){
		$this->kpmb = $kpmb;
	}

	public function __call( $name, $params ){

		if( method_exists( $this->kpmb, $name ) ){
			return $this->kpmb->$name( ...$params );
		}

		return null;
	}

	// sep
	public function sep( $rg, $var, $post ){

		$class = [ 'kpmb__sep' ];
		! $rg->title && $class[] = '--hr';
		$class = implode( ' ', $class );

		if( false !== strpos( $rg->field_patt, '<td' ) ){
			return str_replace( '<td ',
				sprintf( '<td class="%s" colspan="2" %s', $class, $rg->attr ),
				$this->kpmb->tpl__field( $rg->title )
			);
		}

		return sprintf( '<span class="%s" %s>%s</span>', $class, $rg->attr, $rg->title );
	}

	// textarea
	public function textarea( $rg, $var, $post ){
		$_style = ( false === strpos( $rg->attr, 'style=' ) ) ? ' style="width:98%;"' : '';

		$field = sprintf( '<textarea %s id="%s" name="%s">%s</textarea>',
			( $rg->attr . $var->class . $var->pholder . $_style ),
			$rg->id,
			$var->name,
			esc_textarea( $var->val )
		);

		return $var->title . $this->kpmb->tpl__field( $this->kpmb->field_desc_concat( $field ) );
	}

	// select
	public function select( $rg, $var, $post ){

		$is_assoc = ( array_keys($rg->options) !== range(0, count($rg->options) - 1) ); // associative or not?
		$_options = array();
		foreach( $rg->options as $v => $l ){
			$_val       = $is_assoc ? $v : $l;
			$_options[] = '<option value="'. esc_attr($_val) .'" '. selected($var->val, $_val, 0) .'>'. $l .'</option>';
		}

		$field = sprintf( '<select %s id="%s" name="%s">%s</select>',
			( $rg->attr . $var->class ),
			$rg->id,
			$var->name,
			implode("\n", $_options )
		);

		return $var->title . $this->kpmb->tpl__field( $this->kpmb->field_desc_concat( $field ) );
	}

	// radio
	public function radio( $rg, $var, $post ){

		$radios = array();

		foreach( $rg->options as $v => $l ){
			$radios[] = '
			<label '. $rg->attr . $var->class .'>
				<input type="radio" name="'. $var->name .'" value="'. $v .'" '. checked($var->val, $v, 0) .'>'. $l .'
			</label> ';
		}

		$field = '<span class="radios">'. implode("\n", $radios ) .'</span>';

		return $var->title . $this->kpmb->tpl__field( $this->kpmb->field_desc_concat( $field ) );
	}

	/**
	 * Checkbox.
	 *
	 * Examples:
	 *
	 *     [ 'type'=>'checkbox', 'title'=>'Check me', 'desc'=>'mark it if you want to :)' ]
	 *
	 * @param object  $rg
	 * @param object  $var
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function checkbox( $rg, $var, $post ){

		$patt = '
		<label {attrs}>
			<input type="hidden" name="{name}" value="">
			<input type="checkbox" id="{id}" name="{name}" value="{value}" {checked}>
			{desc}
		</label>
		';

		$value = reset( $rg->options ) ?: 1;

		$field = strtr( $patt, [
			'{attrs}'     => $rg->attr . $var->class,
			'{name}'      => $var->name,
			'{id}'        => $rg->id,
			'{value}'     => esc_attr( $value ),
			'{checked}'   => checked( $var->val, $value, 0 ),
			'{desc}'      => $rg->desc_before ?: '',
		] );

		return $var->title . $this->kpmb->tpl__field( $field );
	}

	/**
	 * checkbox multi
	 *
	 * Examples:
	 *
	 *     [
	 *         type => checkbox_multi,
	 *         params => show_inline,
	 *         options => [
	 *             [ name => bar, val => label, desc => The checkbox ]
	 *             [ val => label, desc => The checkbox ]
	 *         ]
	 *     ]
	 *
	 * @param object  $rg
	 * @param object  $var
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function checkbox_multi( $rg, $var, $post ){

		$checkboxes = [];

		foreach( $rg->options as $opt ){

			// val
			// desc
			// name
			$opt = (object) $opt;

			if( ! isset( $opt->desc ) ){
				$opt->desc = $opt->val;
			}

			$input_name  = isset( $opt->name ) ? "{$var->name}[$opt->name]" : "{$var->name}[]";
			$add_hidden  = isset( $opt->name );
			$input_value = $opt->val ?? 1;

			// checked
			$checked = '';
			if( $var->val ){
				if( isset( $opt->name ) ){
					$checked = ! empty( $var->val[ $opt->name ] ) ? 'checked="checked"' : '';
				}
				else{
					$var->val = array_map( fn( $val ) => str_replace( ' ', ' ', $val ), $var->val );
					$checked = in_array( $opt->val, $var->val, true ) ? 'checked="checked"' : '';
				}
			}

			$checkboxes[] = '
				<label>
					'.( $add_hidden ? '<input type="hidden" name="'. $input_name .'" value="">' : '' ).'
					<input type="checkbox" name="'. $input_name .'" value="'. $input_value .'" '. $checked .'> '. $opt->desc .'
				</label>
				';
		}

		$sep = in_array( 'show_inline', $rg->params, true ) ? ' &nbsp;&nbsp; ' : ' <br> ';

		// for the main array
		$common_hidden = $add_hidden ? '' : '<input type="hidden" name="'. $var->name .'" value="">';

		$field = '
		<fieldset>
			<div class="fieldset">'. $common_hidden . implode( "$sep\n", $checkboxes ) .'</div>
		</fieldset>';

		return $var->title . $this->kpmb->tpl__field( $field );
	}

	// hidden
	public function hidden( $rg, $var, $post ){

		return sprintf( '<input type="%s" id="%s" name="%s" value="%s" title="%s">',
			$rg->type,
			$rg->id,
			$var->name,
			esc_attr( $var->val ),
			esc_attr( $rg->title )
		);
	}

	// wp_editor
	public function wp_editor( $rg, $var, $post ){

		$ed_args = array_merge( [
			'textarea_name'    => $var->name, // must be specified!
			'editor_class'     => $rg->class,
			// changeable
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
		wp_editor( $var->val, $rg->id, $ed_args );
		$field = ob_get_clean();

		return $var->title . $this->kpmb->tpl__field( $this->kpmb->field_desc_concat( $field ) );
	}

	// image
	public function image( $rg, $var, $post ){

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

		if( ! $src = is_numeric( $var->val ) ? wp_get_attachment_url( $var->val ) : $var->val ){
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

				<input type="hidden" name="<?= $var->name ?>" value="<?= esc_attr($var->val) ?>">
			</span>
		</span>
		<?php
		$field = ob_get_clean();

		return $var->title . $this->kpmb->tpl__field( $field );
	}

	// text, email, number, url, tel, color, password, date, month, week, range
	public function default( $rg, $var, $post ){

		$_style = ( $rg->type === 'text' && false === strpos( $rg->attr, 'style=' ) )
			? ' style="width:100%;"'
			: '';

		$field = sprintf( '<input %s type="%s" id="%s" name="%s" value="%s" title="%s">',
			( $rg->attr . $var->class  . $var->pholder . $_style ),
			$rg->type,
			$rg->id,
			$var->name,
			esc_attr( $var->val ),
			esc_attr( $rg->title )
		);

		return $var->title . $this->kpmb->tpl__field( $this->kpmb->field_desc_concat( $field ) );
	}

}



