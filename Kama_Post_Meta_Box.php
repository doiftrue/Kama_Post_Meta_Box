<?php

if( class_exists( 'Kama_Post_Meta_Box' ) ){
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
 * Requires PHP: 7.2
 *
 * @changlog https://github.com/doiftrue/Kama_Post_Meta_Box/blob/master/changelog.md
 *
 * @version 1.17
 */
class Kama_Post_Meta_Box {

	use Kama_Post_Meta_Box__Themes;
	use Kama_Post_Meta_Box__Sanitizer;

	/** @var object */
	public $opt;

	/** @var string */
	public $id;

	/** @var array */
	static $instances = array();

	/** @var Kama_Post_Meta_Box_Fields */
	protected $fields_class;

	protected const METABOX_ARGS = [
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

	/**
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
	 *                                               чтобы метабокс отобразился. {@see https://wp-kama.ru/post_type_supports}.
	 *     @type string          $post_type_options  Массив. Опции типа записи, которые должны быть у типа записи,
	 *                                               чтобы метабокс отобразился. {@see https://wp-kama.ru/get_post_types}.
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
	 *         @type string $id                   Атрибут id. По умолчанию: `{$this->opt->id}_{$key}`.
	 *         @type string $class                Атрибут class: добавляется в input, textarea, select.
	 *                                            Для checkbox, radio в оборачивающий label.
	 *         @type string $attr                 Любая строка. Атрибуты HTML тега элемента формы (input).
	 *         @type string $wrap_attr            Любая строка. Атрибуты HTML тега оборачивающего поле: `style="width:50%;"`.
	 *         @type string $val                  Значение по умолчанию, если нет сохраненного.
	 *         @type string $params               Дополнительные параметры поля. У каждого свои (см. код метода поля).
	 *         @type string $options              массив: `array('значение'=>'название')` - варианты для типов `select`, `radio`.
	 *                                            Для 'wp_editor' стенет аргументами.
	 *                                            Для 'checkbox' станет значением атрибута value:
	 *                                            `<input type="checkbox" value="{options}">`.
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

		// do nothing on front
		if( ! is_admin() && ! defined('DOING_AJAX') ){
			return;
		}

		$this->opt = (object) array_merge( self::METABOX_ARGS, $opt );

		$this->set_fields_class();

		// Init hooks hangs on the `init` action, because we need current user to be installed
		add_action( 'init', [ $this, 'init_hooks' ], 20 );
	}

	private function set_fields_class(): void {

		$fields_class = apply_filters( 'kama_post_meta_box__fields_class', '' );

		if( $fields_class ){
			$this->fields_class = new $fields_class();
		}
		else {
			$this->fields_class = new Kama_Post_Meta_Box_Fields();
		}
	}

	public function get_fields_class(): Kama_Post_Meta_Box_Fields {
		return $this->fields_class;
	}

	public function init_hooks(): void {

		// maybe the metabox is disabled by capability.
		if( $this->opt->cap && ! current_user_can( $this->opt->cap ) ){
			return;
		}

		// theme design.
		add_action( 'current_screen', [ $this, '_set_theme' ], 20 );

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

		$this->set_value_sanitize_wp_hook();
	}

	public function add_meta_box( $post_type, $post ): void {

		$opt = $this->opt;

		if( $opt->post_type_options && is_string( $opt->post_type_options ) ){
			$opt->post_type_options = [ $opt->post_type_options => 1 ];
		}

		/** @noinspection NotOptimalIfConditionsInspection */
		if(
			in_array( $post_type, [ 'comment', 'link' ], true )
			|| ! current_user_can( get_post_type_object( $post_type )->cap->edit_post, $post->ID )
			|| ( $opt->post_type_feature && ! post_type_supports( $post_type, $opt->post_type_feature ) )
			|| ( $opt->post_type_options && ! in_array( $post_type, get_post_types( $opt->post_type_options, 'names', 'or' ), true ) )
			|| ( $opt->disable_func && is_callable( $opt->disable_func ) && call_user_func( $opt->disable_func, $post ) )
			|| in_array( $post_type, (array) $opt->not_post_type, true )
		){
			return;
		}

		$p_types = $opt->post_type ?: $post_type;

		add_meta_box( $this->id, $opt->title, [ $this, 'meta_box_html' ], $p_types, $opt->context, $opt->priority );

		// добавим css класс к метабоксу
		// apply_filters( "postbox_classes_{$page}_{$id}", $classes );
		add_filter( "postbox_classes_{$post_type}_{$this->id}", [ $this, 'add_metabox_css_classes' ] );
	}

	/**
	 * Displays the HTML code of the meta block.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function meta_box_html( $post ): void {

		$fields_out = '';
		$hidden_out = '';

		/** @var array $args For phpstan */
		foreach( $this->opt->fields as $key => $args ){

			// empty field
			if( ! $key || ! $args ){
				continue;
			}

			empty( $args['title_patt'] )       && ( $args['title_patt'] = $this->opt->title_patt ?? '%s' );
			empty( $args['desc_before_patt'] ) && ( $args['desc_before_patt']  = $this->opt->desc_before_patt ?? '%s' );
			empty( $args['field_patt'] )       && ( $args['field_patt'] = $this->opt->field_patt ?? '%s' );

			$args['key'] = $key;
			$field_type = $args['type'] ?? '';

			$field_wrap = & $this->opt->field_wrap;
			if( 'wp_editor' === $field_type ){
				$field_wrap = str_replace( [ '<p ', '</p>' ], [ '<div ', '</div><br>' ], $field_wrap );
			}

			$Field = new Kama_Post_Meta_Box__Field_Core( $this );
			$this->fields_class->set_current_field_core( $Field );

			if( 'hidden' === $field_type ){
				$hidden_out .= $Field->field_html( $args, $post );
			}
			else {
				$fields_out .= sprintf( $field_wrap,
					"{$key}_meta",
					$Field->field_html( $args, $post ),
					( $args['wrap_attr'] ?? '' )
				);
			}

		}

		$metabox_desc = '';
		if( $this->opt->desc ){
			$metabox_desc = is_callable( $this->opt->desc )
				? call_user_func( $this->opt->desc, $post )
				: '<p class="description">' . $this->opt->desc . '</p>';
		}

		$style = $this->opt->css ? "<style>{$this->opt->css}</style>" : '';

		echo $style;
		echo $metabox_desc;
		echo $hidden_out;
		echo sprintf( ( $this->opt->fields_wrap ?: '%s' ), $fields_out );
		echo '<div class="clearfix"></div>';
	}

	/**
	 * Saving data, when saving a post.
	 *
	 * @param int      $post_id Record ID.
	 * @param \WP_Post $post
	 *
	 * @return void False If the check is not passed.
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
		$fields_data = [];
		foreach( $this->opt->fields as $_key => $rg ){
			$meta_key = $this->key_prefix() . $_key;

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
		$save_metadata = $this->maybe_run_custom_sanitize( $save_metadata, $post_id, $fields_data );

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

	public function add_metabox_css_classes( $classes ){

		$classes[] = "kama_meta_box_{$this->opt->id}";

		return $classes;
	}

	public function key_prefix(): string {
		return ( '_' === $this->opt->id[0] ) ? '' : "{$this->opt->id}_";
	}

}

/**
 * Prepare single field for render.
 */
class Kama_Post_Meta_Box__Field_Core {

	protected const FIELD_ARGS =  [
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

	protected $rg;
	protected $post;
	protected $var;

	/** @var Kama_Post_Meta_Box */
	protected $kpmb;


	public function __construct( Kama_Post_Meta_Box $kpmb ){
		$this->kpmb = $kpmb;
	}

	/**
	 * Outputs individual meta field.
	 *
	 * @param array   $args  Field parameters.
	 * @param WP_Post $post  The object of the current post.
	 *
	 * @return string HTML code.
	 */
	public function field_html( array $args, $post ): string {

		$this->post = $post;

		$this->rg = $this->parse_args( $args );

		// no acces to the field
		if( ! $this->rg ){
			return '';
		}

		$this->var = $this->create_field_vars();

		$this->standartize_rg_desc(); // !!! after var set

		return $this->field_output( $args );
	}

	public function tpl__field( string $field ): string {

		return sprintf( $this->rg->field_patt, $field );
	}

	public function field_desc_concat( string $field ): string{

		$rg = $this->rg;
		$opt = $this->kpmb->opt;

		// description before field
		if( $rg->desc_before ){
			$desc = sprintf( $opt->desc_before_patt, $rg->desc_before );

			return $desc . $field;
		}

		// descroption after field
		if( $rg->desc_after ){
			$desc = sprintf( $opt->desc_after_patt, $rg->desc_after );

			return $field . $desc;
		}

		return $field;
	}


	/**
	 * Parse fields arguments.
	 *
	 * @return object|null Null if user can access to see meta-field
	 */
	private function parse_args( $args ): ?object {

		$rg = (object) array_merge( self::FIELD_ARGS, $args );

		if( $rg->cap && ! current_user_can( $rg->cap ) ){
			return null;
		}

		$rg->meta_key = $this->kpmb->key_prefix() . $rg->key;

		// the field is disabled
		if(
			$rg->disable_func
			&& is_callable( $rg->disable_func )
			&& call_user_func( $rg->disable_func, $this->post, $rg->meta_key )
		){
			return null;
		}

		// fix some fields $rg

		$rg->id = $rg->id ?: "{$this->kpmb->opt->id}_{$rg->key}";
		$rg->options = (array) $rg->options;

		if( 0 === strpos( $rg->key, 'sep_' ) ){
			$rg->type = 'sep';
		}

		if( ! $rg->type ){
			$rg->type = 'text';
		}

		return $rg;
	}

	private function create_field_vars(): object {

		$post = $this->post;
		$rg = $this->rg;

		// internal variables of this function, will be transferred to the methods
		$var = new \stdClass();

		$var->meta_key = $rg->meta_key;

		$var->val = get_post_meta( $post->ID, $var->meta_key, true ) ?: $rg->val;
		if( $rg->output_func && is_callable( $rg->output_func ) ){
			$var->val = call_user_func( $rg->output_func, $post, $var->meta_key, $var->val );
		}

		$var->name = "{$this->kpmb->id}_meta[$var->meta_key]";

		// with a table theme, the td header should always be output!
		if( false !== strpos( $rg->title_patt, '<td ' ) ){
			$var->title = sprintf( $rg->title_patt, $rg->title ) . ( $rg->title ? ' ' : '' );
		}
		else{
			$var->title = $rg->title ? sprintf( $rg->title_patt, $rg->title ) . ' ' : '';
		}

		$var->pholder = $rg->placeholder ? ' placeholder="'. esc_attr( $rg->placeholder ) .'"' : '';
		$var->class = $rg->class ? ' class="'. esc_attr( $rg->class ) .'"' : '';

		return $var;
	}

	private function field_output( $args ): string {

		$rg = & $this->rg;
		$post = & $this->post;
		$var = & $this->var;

		// custom function
		if( is_callable( $rg->callback ) ){
			$out = $var->title;
			$out .= $this->tpl__field(
				call_user_func( $rg->callback, $args, $post, $var->name, $var->val, $rg, $var )
			);
		}
		// custom method
		// Call the method `$this->field__{FIELD}()` (to be able to extend this class)
		elseif( method_exists( $this->kpmb->get_fields_class(), $rg->type ) ){
			$out = $this->kpmb->get_fields_class()->{ $rg->type }( $rg, $var, $post, $args );
		}
		// text, email, number, url, tel, color, password, date, month, week, range
		else{
			$out = $this->kpmb->get_fields_class()->default( $rg, $var, $post );
		}

		return $out;
	}

	private function standartize_rg_desc(): void {

		$rg = & $this->rg;

		if( $rg->desc ){
			$rg->desc_before = $rg->desc;
		}

		if( ! $rg->desc && ! $rg->desc_before && $rg->desc_after ){
			$rg->desc = $rg->desc_after;
		}

		foreach( [ & $rg->desc, & $rg->desc_before, & $rg->desc_after ] as & $desc ){

			if( is_callable( $desc ) ){
				$desc = $desc( $this->post, $this->var->meta_key, $this->var->val, $this->var->name );
			}
		}
	}

}

/**
 * Separate class which contains fields.
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

	/**
	 * Changable property. Contains class instance of single field (data) that processing now.
	 *
	 * @var Kama_Post_Meta_Box__Field_Core
	 */
	protected $the_field;

	public function __construct(){
	}

	public function set_current_field_core( Kama_Post_Meta_Box__Field_Core $class ): void {
		$this->the_field = $class;
	}

	protected function tpl__field( string $field ): string {
		return $this->the_field->tpl__field( $field );
	}

	protected function field_desc_concat( string $field ): string {
		return $this->the_field->field_desc_concat( $field );
	}

	/**
	 * Sep field.
	 *
	 * Example:
	 *
	 *     'sep_1' => [
	 *         'title' => 'SEO headers',
	 *         'desc'  => fn( $post ) => 'Placeholders: ' .  placeholders(),
	 *     ],
	 *
	 * @param object  $rg
	 * @param object  $var
	 * @param WP_Post $post
	 *
	 * @return array|string|string[]
	 */
	public function sep( object $rg, object $var, $post ){

		$class = [ 'kpmb__sep' ];
		! $rg->title && $class[] = '--hr';
		$class = implode( ' ', $class );

		// table theme

		if( false !== strpos( $rg->field_patt, '<td' ) ){

			$field = $rg->title;

			if( $rg->desc ){
				$field .= sprintf( '<div class="kpmb__sep-desc">%s</div>', $rg->desc );
			}

			return str_replace(
				'<td ',
				sprintf( '<td class="%s" colspan="2" %s', $class, $rg->attr ),
				$this->tpl__field( $field )
			);
		}

		// other theme

		$sep = sprintf( '<span class="%s" %s>%s</span>', $class, $rg->attr, $rg->title );

		if( $rg->desc ){
			$sep .= sprintf( '<span class="kpmb__sep-desc">%s</span>', $rg->desc );
		}

		return $sep;
	}

	// textarea
	public function textarea( object $rg, object $var, WP_Post $post ): string {
		$_style = ( false === strpos( $rg->attr, 'style=' ) ) ? ' style="width:98%;"' : '';

		$field = sprintf( '<textarea %s id="%s" name="%s">%s</textarea>',
			( $rg->attr . $var->class . $var->pholder . $_style ),
			$rg->id,
			$var->name,
			esc_textarea( $var->val )
		);

		$field = $this->field_desc_concat( $field );

		return $var->title . $this->tpl__field( $field );
	}

	// select
	public function select( object $rg, object $var, WP_Post $post ): string {

		$is_assoc = ( array_keys($rg->options) !== range(0, count($rg->options) - 1) ); // associative or not?
		$_options = array();
		foreach( $rg->options as $v => $l ){
			$_val       = $is_assoc ? $v : $l;
			$_options[] = '<option value="'. esc_attr($_val) .'" '. selected($var->val, $_val, false) .'>'. $l .'</option>';
		}

		$field = sprintf( '<select %s id="%s" name="%s">%s</select>',
			( $rg->attr . $var->class ),
			$rg->id,
			$var->name,
			implode("\n", $_options )
		);

		$field = $this->field_desc_concat( $field );

		return $var->title . $this->tpl__field( $field );
	}

	/**
	 * radio.
	 *
	 * Examples:
	 *
	 *     'meta_name' => [
	 *         'type'    => 'radio',
	 *         'title'   => 'Check me',
	 *         'desc'    => 'mark it',
	 *         'options' => [ 'on' => 'Enabled', 'off' => 'Disabled' ],
	 *     ]
	 *
	 * @param object  $rg
	 * @param object  $var
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function radio( object $rg, object $var, WP_Post $post ): string {

		$radios = [];

		$patt = '
		<label {attrs}>
			<input type="radio" id="{id}" name="{name}" value="{value}" {checked}>
			{label}
		</label>
		';

		foreach( $rg->options as $value => $label ){

			$radios[] = strtr( $patt, [
				'{attrs}'   => $rg->attr . $var->class,
				'{name}'    => $var->name,
				'{id}'      => $rg->id,
				'{value}'   => esc_attr( $value ),
				'{checked}' => checked( $var->val, $value, false ),
				'{label}'   => $label,
			] );
		}

		$field = '<span class="radios">'. implode( "\n", $radios ) .'</span>';

		$field = $this->field_desc_concat( $field );

		return $var->title . $this->tpl__field( $field );
	}

	/**
	 * Checkbox.
	 *
	 * Examples:
	 *
	 *     ```
	 *     'meta_name' => [ 'type'=>'checkbox', 'title'=>'Check me', 'desc'=>'mark it if you want to :)' ]
	 *     'meta_name' => [ 'type'=>'checkbox', 'title'=>'Check me', 'options' => [ 'default' => '0' ]  ]
	 *     ```
	 */
	public function checkbox( object $rg, object $var, \WP_Post $post ): string {

		$patt = '
		<label {attrs}>
			<input type="hidden" name="{name}" value="{default}">
			<input type="checkbox" id="{id}" name="{name}" value="{value}" {checked}>
			{desc}
		</label>
		';

		$value = reset( $rg->options ) ?: 1;

		$field = strtr( $patt, [
			'{attrs}'   => $rg->attr . $var->class,
			'{name}'    => $var->name,
			'{default}' => $rg->params['default'] ?? '',
			'{id}'      => $rg->id,
			'{value}'   => esc_attr( $value ),
			'{checked}' => checked( $var->val, $value, false ),
			'{desc}'    => $rg->desc_before ?: '',
		] );

		return $var->title . $this->tpl__field( $field );
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
	public function checkbox_multi( object $rg, object $var, WP_Post $post ): string {

		$checkboxes = [];
		$add_hidden = false;

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

		return $var->title . $this->tpl__field( $field );
	}

	// hidden
	public function hidden( object $rg, object $var, WP_Post $post ): string {

		return sprintf( '<input type="%s" id="%s" name="%s" value="%s" title="%s">',
			$rg->type,
			$rg->id,
			$var->name,
			esc_attr( $var->val ),
			esc_attr( $rg->title )
		);
	}

	// wp_editor
	public function wp_editor( object $rg, object $var, WP_Post $post ): string {

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

		$field = $this->field_desc_concat( $field );

		return $var->title . $this->tpl__field( $field );
	}

	// image
	public function image( object $rg, object $var, WP_Post $post ): string {

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

		return $var->title . $this->tpl__field( $field );
	}

	// text, email, number, url, tel, color, password, date, month, week, range
	public function default( object $rg, object $var, WP_Post $post ): string {

		$_style = ( in_array( $rg->type, [ 'text', 'url' ], true ) && false === strpos( $rg->attr, 'style=' ) )
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

		$field = $this->field_desc_concat( $field );

		return $var->title . $this->tpl__field( $field );
	}

}

trait Kama_Post_Meta_Box__Themes {

	private function themes_settings(): array {

		return [
			'line' => [
				// CSS styles of the whole block. For example: '.postbox .tit{ font-weight:bold; }'
				'css'         => '
					.kpmb{ display: flex; flex-wrap: wrap; justify-content: space-between; }
					.kpmb > * { width:100%; }
					.kpmb__field{ box-sizing:border-box; margin-bottom:1em; }
					.kpmb__tit{ display: block; margin:1em 0 .5em; font-size:115%; }
					.kpmb__desc{ opacity:0.6; }
					.kpmb__desc.--after{ margin-top:.5em; }
					.kpmb__sep{ display: block; padding: 2em 1em 0.2em 0; font-size: 130%; font-weight: 600; }
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
					.kpmb-table td{ padding: .6em .5em; }
					.kpmb-table tr:hover{ background: rgba(0,0,0,.03); }
					.kpmb__sep{ padding: 1em .5em; font-weight: 600; }
					.kpmb__sep-desc{ padding-top: .3em; font-weight: normal; opacity: .6; }
					.kpmb__desc{ opacity: 0.8; }
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
					.kpmb-grid{ margin: '. ( get_current_screen()->is_block_editor ? '-6px -24px -24px' : '-6px -12px -12px' ) .' }
					.kpmb-grid__item{ display:grid; grid-template-columns:15em 2fr; grid-template-rows:1fr; border-bottom:1px solid rgba(0,0,0,.1) }
					.kpmb-grid__item:last-child{ border-bottom:none }
					.kpmb-grid__title{ padding:1.5em; background:#F9F9F9; border-right:1px solid rgba(0,0,0,.1); font-weight:600 }
					.kpmb-grid__field{ align-self:center; padding:1em 1.5em }
					.kpmb__sep{ grid-column: 1 / span 2; display:block; padding:1em; font-size:110%; font-weight:600; }
					.kpmb__sep-desc{ grid-column: 1 / span 2; display: block; padding: 0 1em 1em 1em; opacity: .7; }
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

	}

	public function _set_theme(): void {

		$themes_settings = $this->themes_settings();

		$opt_theme = & $this->opt->theme;

		if( is_string( $opt_theme ) ){
			$themes_settings = $themes_settings[ $opt_theme ];
		}
		// allows you to change individual option (field) of the theme option.
		else {
			$opt_theme_key = key( $opt_theme );

			// theme is in the index: [ 'table' => [ 'desc_before_patt' => '<div>%s</div>' ] ]
			if( isset( $themes_settings[ $opt_theme_key ] ) ){
				$themes_settings = $themes_settings[ $opt_theme_key ]; // base
				$opt_theme     = $opt_theme[ $opt_theme_key ];
			}
			// not theme in the index: [ 'desc_before_patt' => '<div>%s</div>' ]
			else {
				$themes_settings = $themes_settings['line']; // base
			}
		}

		$opt_theme = is_array( $opt_theme ) ? array_merge( $themes_settings, $opt_theme ) : $themes_settings;

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

trait Kama_Post_Meta_Box__Sanitizer {

	/**
	 * Checks and run custom sanitize callback.
	 */
	protected function maybe_run_custom_sanitize( array $save_metadata, $post_id, $fields_data ){

		// Own sanitizing.
		if( is_callable( $this->opt->save_sanitize ) ){
			return call_user_func( $this->opt->save_sanitize, $save_metadata, $post_id, $fields_data );
		}

		// Sanitizing hook.
		if( has_filter( "kpmb_save_sanitize_{$this->opt->id}" ) ){
			return apply_filters( "kpmb_save_sanitize_{$this->opt->id}", $save_metadata, $post_id, $fields_data );
		}

		/**
		 * INFO: Other sanitization is hanged on wp_hook.
		 * {@see set_value_sanitize_wp_hook()}
		 */

		return $save_metadata;
	}

	/**
	 * Sets wp hooks to sinitize values based on specified function or default function.
	 */
	private function set_value_sanitize_wp_hook(): void {

		// Own sanitizing - this sanitization do only on edit post page. TODO: move it here.
		if( is_callable( $this->opt->save_sanitize ) || has_filter( "kpmb_save_sanitize_{$this->opt->id}" ) ){
			return;
		}

		foreach( $this->opt->fields as $field_key => $field ){
			// empty field
			if( ! $field_key || ! $field ){
				continue;
			}

			$field_sanitize_func = $field['sanitize_func'] ?? null;

			// do not clean
			if( 'none' === $field_sanitize_func || 'no' === $field_sanitize_func ){
				continue;
			}

			$meta_key = $this->key_prefix() . $field_key;
			$type = $field['type'] ?? 'text';

			// there is a function for cleaning a separate field
			if( is_callable( $field_sanitize_func ) ){
				add_filter( "sanitize_post_meta_{$meta_key}", $field_sanitize_func, 10, 1 );
			}
			elseif( 'number' === $type ){
				add_filter( "sanitize_post_meta_{$meta_key}", [ __CLASS__, '_sanitize_val__number' ], 10, 1 );
			}
			elseif( 'url' === $type ){
				add_filter( "sanitize_post_meta_{$meta_key}", 'sanitize_url', 10, 1 );
			}
			elseif( 'email' === $type ){
				add_filter( "sanitize_post_meta_{$meta_key}", 'sanitize_email', 10, 1 );
			}
			elseif( in_array( $type, [ 'wp_editor', 'textarea' ], true ) ){
				add_filter( "sanitize_post_meta_{$meta_key}", [ __CLASS__, '_sanitize_val__textarea' ], 10, 1 );
			}
			else {
				add_filter( "sanitize_post_meta_{$meta_key}", [ __CLASS__, '_sanitize_val__default' ], 10, 1 );
			}

		}
	}

	public static function _sanitize_val__number( $value ){
		return is_float( $value + 0 ) ? (float) $value : (int) $value;
	}

	public static function _sanitize_val__textarea( $value ){
		return wp_kses_post( $value );
	}

	public static function _sanitize_val__default( $value ){

		// do not clean - apparently it is an arbitrary field output function that saves an array
		if( is_array( $value ) ){
			return $value;
		}

		$value = sanitize_text_field( $value );

		return $value;
	}

}


