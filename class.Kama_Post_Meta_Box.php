<?php

if( ! class_exists('Kama_Post_Meta_Box') ) :

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
	 * PHP: 5.3+
	 *
	 * @changlog https://github.com/doiftrue/Kama_Post_Meta_Box/blob/master/changelog.md
	 *
	 * @version 1.9.10
	 */
	class Kama_Post_Meta_Box {

		public $opt;

		public $id;

		static $instances = array();

		/**
		 * Конструктор.
		 *
		 * @param array $opt Опции по которым будет строиться метаблок.
		 */
		function __construct( $opt ){

			$defaults = [
				'id'         => '',       // динетификатор блока. Используется как префикс для названия метаполя.
				// начните идент. с '_': '_foo', чтобы ID не был префиксом в названии метаполей.

				'title'      => '',       // заголовок блока
				'desc'       => '',       // описание для метабокса. Можно указать функцию/замыкание, она получит $post. С версии 1.9.1
				'post_type'  => '',       // строка/массив. Типы записей для которых добавляется блок: array('post','page').
				'not_post_type'  => '',   // строка/массив. Типы записей для которых не нужно добавлять этот блок.
				// По умолчанию: '' - для всех типов записей.
				'post_type_feature' => '', // строка. возможность которая должна быть у типа записи,
				// чтобы метабокс отобразился. См. post_type_supports()
				'post_type_options' => '', // массив. опции типа записи, которые должны быть у типа записи чтобы метабокс отобразился.
				// см. первый параметр get_post_types()

				'priority'   => 'high',   // Приоритет блока для показа выше или ниже остальных блоков ('high' или 'low').
				'context'    => 'normal', // Место где должен показываться блок ('normal', 'advanced' или 'side').

				'disable_func'  => '',    // функция отключения метабокса во время вызова самого метабокса.
				// Если вернет что-либо кроме false/null/0/array(), то метабокс будет отключен. Передает объект поста.

				'cap'           => '',    // название права пользователя, чтобы показывать метабокс.

				'save_sanitize' => '',    // Функция очистки сохраняемых в БД полей. Получает 2 параметра:
				// $metas - все поля для очистки и $post_id

				'theme' => 'table',       // тема оформления: 'table', 'line'.
				// ИЛИ массив паттернов полей: css, fields_wrap, field_wrap, title_patt, field_patt, desc_patt.
				// Массив указывается так: [ 'desc_patt' => '<div>%s</div>' ] (за овнову будет взята тема line)
				// Массив указывается так: [ 'table' => [ 'desc_patt' => '<div>%s</div>' ] ] (за овнову будет взята тема table)
				// ИЛИ изменить тему можно через фильтр 'kp_metabox_theme' - удобен для общего изменения темы для всех метабоксов.

				// метаполя. Параметры смотрите ниже в методе field()
				'fields'     => [
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
			if( $this->opt->cap && ! current_user_can( $this->opt->cap ) )
				return;

			// темы оформления
			if( 'themes' ){

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
						'desc_patt'   => '<br><span class="description" style="opacity:0.6;">%s</span>',
					],
					'table' => [
						'css'         => '.kpmb-table td{ padding:.6em .5em; } .kpmb-table tr:hover{ background:rgba(0,0,0,.03); }',
						'fields_wrap' => '<table class="form-table kpmb-table">%s</table>',
						'field_wrap'  => '<tr class="%1$s">%2$s</tr>',
						'title_patt'  => '<td style="width:10em;" class="tit">%s</td>',
						'field_patt'  => '<td class="field">%s</td>',
						'desc_patt'   => '<br><span class="description" style="opacity:0.8;">%s</span>',
					],
				];

				if( is_string($opt_theme) ){
					$def_opt_theme = $def_opt_theme[ $opt_theme ];
				}
				// позволяет изменить отдельные поля темы оформелния метабокса
				else {
					$opt_theme_key = key( $opt_theme ); // индекс массива

					// в индексе указана не тема: [ 'desc_patt' => '<div>%s</div>' ]
					if( ! in_array( $opt_theme_key, array_keys($def_opt_theme) ) ){
						$def_opt_theme = $def_opt_theme['line']; // основа темы
					}
					// в индексе указана тема: [ 'table' => [ 'desc_patt' => '<div>%s</div>' ] ]
					else {
						$def_opt_theme = $def_opt_theme[ $opt_theme_key ]; // основа темы
						$opt_theme     = $opt_theme[ $opt_theme_key ];
					}
				}

				$opt_theme = is_array( $opt_theme ) ? array_merge( $def_opt_theme, $opt_theme ) : $def_opt_theme;

				// для изменения темы через фильтр
				$opt_theme = apply_filters( 'kp_metabox_theme', $opt_theme, $this->opt );

				// переменные theme в общие параметры.
				// Если в параметрах уже есть переменная, то она остается как есть (это позволяет изменить отдельный элемент темы).
				foreach( $opt_theme as $kk => $vv ){
					if( ! isset($this->opt->{$kk}) )
						$this->opt->{$kk} = $vv;
				}
			}

			// создадим уникальный ID объекта
			$_opt = (array) clone $this->opt;
			// удалим (очистим) все closure
			array_walk_recursive( $_opt, function(&$val, $key){
				if( $val instanceof Closure ) $val = '';
			});
			$this->id = substr( md5(serialize($_opt)), 0, 7 ); // ID экземпляра

			// сохраним ссылку на экземпляр, чтобы к нему был доступ
			self::$instances[ $this->opt->id ][ $this->id ] = & $this;

			add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
			add_action( 'save_post', [ $this, 'meta_box_save' ], 1, 2 );
		}

		function add_meta_box( $post_type, $post ){

			$opt = $this->opt;

			if(
				in_array( $post_type, [ 'comment','link' ] )
				|| ! current_user_can( get_post_type_object( $post_type )->cap->edit_post, $post->ID )
				|| ( $opt->post_type_feature && ! post_type_supports( $post_type, $opt->post_type_feature ) )
				|| ( $opt->post_type_options && ! in_array( $post_type, get_post_types( $opt->post_type_options, 'names', 'or' ) ) )
				|| ( $opt->disable_func && is_callable($opt->disable_func) && call_user_func( $opt->disable_func, $post ) )
				|| in_array( $post_type, (array) $opt->not_post_type, true )
			)
				return;

			$p_types = $opt->post_type ?: $post_type;

			// if WP < 4.4
			if( is_array($p_types) && version_compare( $GLOBALS['wp_version'], '4.4', '<' ) ){
				foreach( $p_types as $p_type )
					add_meta_box( $this->id, $opt->title, [ $this, 'meta_box' ], $p_type, $opt->context, $opt->priority );
			}
			else {
				add_meta_box( $this->id, $opt->title, [ $this, 'meta_box' ], $p_types, $opt->context, $opt->priority );
			}

			// добавим css класс к метабоксу
			// apply_filters( "postbox_classes_{$page}_{$id}", $classes );
			add_filter( "postbox_classes_{$post_type}_{$this->id}", [ $this, '_postbox_classes_add' ] );
		}

		/**
		 * Выводит код блока
		 * @param object $post Объект записи
		 */
		function meta_box( $post ){
			$fields_out = $hidden_out = '';

			foreach( $this->opt->fields as $key => $args ){
				if( ! $key || ! $args ) continue; // пустое поле

				if( empty($args['title_patt']) ) $args['title_patt'] = @ $this->opt->title_patt ?: '%s';
				if( empty($args['desc_patt'])  ) $args['desc_patt']  = @ $this->opt->desc_patt  ?: '%s';
				if( empty($args['field_patt']) ) $args['field_patt'] = @ $this->opt->field_patt ?: '%s';

				$args['key'] = $key;

				$field_wrap = & $this->opt->field_wrap;
				if( 'wp_editor' === @ $args['type'] )
					$field_wrap = str_replace( [ '<p ','</p>' ], [ '<div ','</div><br>' ], $field_wrap );

				if( 'hidden' === @ $args['type'] )
					$hidden_out .= $this->field( $args, $post );
				else
					$fields_out .= sprintf( $field_wrap, $key .'_meta', $this->field( $args, $post ) );

			}

			$metabox_desc = '';
			if( $this->opt->desc )
				$metabox_desc = is_callable($this->opt->desc) ? call_user_func($this->opt->desc, $post) : '<p class="description">'. $this->opt->desc .'</p>';

			echo ( $this->opt->css ? '<style>'. $this->opt->css .'</style>' : '' ) .
			     $metabox_desc .
			     $hidden_out .
			     sprintf( (@ $this->opt->fields_wrap ?: '%s'), $fields_out ) .
			     '<div class="clearfix"></div>';
		}

		/**
		 * Выводит отдельные мета поля.
		 *
		 * @param string  $name  Атрибут name.
		 * @param array   $args  Параметры поля.
		 * @param object  $post  Объект текущего поста.
		 *
		 * @return string|null HTML код
		 */
		function field( $args, $post ){

			$var = (object) []; // внутренние переменные этой фукнции, будут переданы в методы
			$rg  = (object) array_merge( [
				'type'          => '', // тип поля: textarea, select, checkbox, radio, image, wp_editor, hidden, sep_*.
				// Или базовые: text, email, number, url, tel, color, password, date, month, week, range.
				// 'sep' - визуальный разделитель, для него нужно указать `title` и можно указать `'attr'=>'style="свои стили"'`.
				// 'sep' - чтобы удобнее указывать тип 'sep' начните ключ поля с `sep_`: 'sep_1' => [ 'title'=>'Разделитель' ].
				// Для типа `image` можно указать тип сохраняемого значения в `options`: 'options'=>'url'. По умолчанию тип = id.
				// По умолчанию 'text'.

				'title'         => '', // заголовок метаполя
				'desc'          => '', // описание для поля. Можно указать функцию/замыкание, она получит параметры: $post, $meta_key, $val, $name.
				'placeholder'   => '', // атрибут placeholder
				'id'            => '', // атрибут id. По умолчанию: $this->opt->id .'_'. $key
				'class'         => '', // атрибут class: добавляется в input, textarea, select. Для checkbox, radio в оборачивающий label
				'attr'          => '', // любая строка, будет расположена внутри тега. Для создания атрибутов. Пр: style="width:100%;"
				'val'           => '', // значение по умолчанию, если нет сохраненного.
				'options'       => '', // массив: array('значение'=>'название') - варианты для типов 'select', 'radio'.
				// Для 'wp_editor' стенет аргументами.
				// Для 'checkbox' станет значением атрибута value: <input type="checkbox" value="{options}">.
				// Для 'image' определяет тип сохраняемого в метаполе значения: id (ID вложения), url (url вложения).

				'callback'      => '', // название функции, которая отвечает за вывод поля.
				// если указана, то ни один параметр не учитывается и за вывод полностью отвечает указанная функция.
				// Все параметры передаются ей... Получит параметры: $args, $post, $name, $val

				'sanitize_func' => '', // функция очистки данных при сохранении - название функции или Closure.
				// Укажите 'none', чтобы не очищать данные...
				// работает, только если не установлен глобальный параметр 'save_sanitize'...
				// получит параметр $value - сохраняемое значение поля.

				'output_func'   => '', // функция обработки значения, перед выводом в поле.
				// получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.

				'update_func'   => '', // функция сохранения значения в метаполя.
				// получит параметры: $post, $meta_key, $value - объект записи, ключ, значение метаполей.

				'disable_func'  => '', // функция отключения поля.
				// Если не false/null/0/array() - что-либо вернет, то поле не будет выведено.
				// Получает парамтры: $post, $meta_key

				'cap'           => '', // название права пользователя, чтобы видеть и изменять поле.

				// служебные
				'key'           => '', // Обязательный! Автоматический
				'title_patt'    => '', // Обязательный! Автоматический
				'field_patt'    => '', // Обязательный! Автоматический
				'desc_patt'     => '', // Обязательный! Автоматический
			], $args );

			if( $rg->cap && ! current_user_can( $rg->cap ) )
				return null;

			if( 'sep_' === substr($rg->key, 0, 4) ) $rg->type = 'sep';
			if( ! $rg->type )                       $rg->type = 'text';

			$var->meta_key = $this->_key_prefix() . $rg->key;

			// поле отключено
			if( $rg->disable_func && is_callable($rg->disable_func) && call_user_func( $rg->disable_func, $post, $var->meta_key ) )
				return null;

			// meta_val
			$rg->val = get_post_meta( $post->ID, $var->meta_key, true ) ?: $rg->val;
			if( $rg->output_func && is_callable($rg->output_func) )
				$rg->val = call_user_func( $rg->output_func, $post, $var->meta_key, $rg->val );

			$var->name = $this->id . "_meta[$var->meta_key]";

			$rg->id  = $rg->id ?: ( $this->opt->id .'_'. $rg->key );

			// при табличной теме, td заголовка должен выводиться всегда!
			if( false !== strpos($rg->title_patt, '<td ') )
				$var->title = sprintf( $rg->title_patt, $rg->title ) . ($rg->title ? ' ' : '');
			else
				$var->title = $rg->title ? sprintf( $rg->title_patt, $rg->title ) .' ' : '';

			$rg->options = (array) $rg->options;

			$var->pholder = $rg->placeholder ? ' placeholder="'. esc_attr($rg->placeholder) .'"' : '';
			$var->class = $rg->class ? ' class="'. $rg->class .'"' : '';

			$fn__desc = function() use ( $rg, $post, $var ){
				if( ! $rg->desc ) return '';
				$desc = is_callable( $rg->desc ) ? call_user_func_array($rg->desc, [ $post, $var->meta_key, $rg->val, $var->name ] ) : $rg->desc;
				return sprintf( $rg->desc_patt, $desc );
			};

			$fn__field = function( $field ) use ( $rg ){
				return sprintf( $rg->field_patt, $field );
			};

			// произвольная функция
			if( is_callable( $rg->callback ) )
				$out = $var->title . $fn__field( call_user_func_array( $rg->callback, [ $args, $post, $var->name, $rg->val, $rg, $var ] ) );
			// произвольный метод
			// вызов метода `$this->field__{FIELD}()` (для возможности расширить этот класс)
			elseif( method_exists( $this, "field__$rg->type") )
				$out = $this->{"field__$rg->type"}( $rg, $var, $post, $fn__desc, $fn__field );
			// text, email, number, url, tel, color, password, date, month, week, range
			else
				$out = $this->field__default( $rg, $var, $post, $fn__desc, $fn__field );

			return $out;
		}

		// textarea
		function field__textarea( $rg, $var, $post, $fn__desc, $fn__field ){
			$_style = (false === strpos($rg->attr,'style=')) ? ' style="width:98%;"' : '';

			return $var->title . $fn__field('<textarea '. $rg->attr . $var->class . $var->pholder . $_style .'  id="'. $rg->id .'" name="'. $var->name .'">'. esc_textarea($rg->val) .'</textarea>'. $fn__desc() );
		}

		// select
		function field__select( $rg, $var, $post, $fn__desc, $fn__field ){
			$is_assoc = ( array_keys($rg->options) !== range(0, count($rg->options) - 1) ); // ассоциативный или нет?
			$_options = array();
			foreach( $rg->options as $v => $l ){
				$_val       = $is_assoc ? $v : $l;
				$_options[] = '<option value="'. esc_attr($_val) .'" '. selected($rg->val, $_val, 0) .'>'. $l .'</option>';
			}

			return $var->title . $fn__field('<select '. $rg->attr . $var->class .' id="'. $rg->id .'" name="'. $var->name .'">' . implode("\n", $_options ) . '</select>' . $fn__desc() );
		}

		// radio
		function field__radio( $rg, $var, $post, $fn__desc, $fn__field ){
			$radios = array();
			foreach( $rg->options as $v => $l ){
				$radios[] = '
				<label '. $rg->attr . $var->class .'>
					<input type="radio" name="'. $var->name .'" value="'. $v .'" '. checked($rg->val, $v, 0) .'>'. $l .'
				</label> ';
			}

			return $var->title . $fn__field('<span class="radios">'. implode("\n", $radios ) .'</span>'. $fn__desc() );
		}

		// checkbox
		function field__checkbox( $rg, $var, $post, $fn__desc, $fn__field ){
			return $var->title . $fn__field('
				<label '. $rg->attr . $var->class .'>
					<input type="hidden" name="'. $var->name .'" value="">
					<input type="checkbox" id="'. $rg->id .'" name="'. $var->name .'" value="'. esc_attr(reset($rg->options) ?: 1) .'" '. checked( $rg->val, (reset($rg->options) ?: 1), 0) .'>
					'.( $rg->desc ?: '' ).'
				</label>');
		}

		// sep
		function field__sep( $rg, $var, $post, $fn__desc, $fn__field ){
			$_style = 'font-weight:600; ';
			if( preg_match( '/style="([^"]+)"/', $rg->attr, $mm ) ) $_style .= $mm[1];

			if( false !== strpos( $rg->field_patt, '<td' ) )
				return str_replace( '<td ', '<td colspan="2" style="padding:1em .5em; '. $_style .'"', $fn__field( $rg->title ) );
			else
				return '<span style="display:block; padding:1em 0; font-size:110%; '. $_style .'">'. $rg->title .'</span>';
		}

		// hidden
		function field__hidden( $rg, $var, $post, $fn__desc, $fn__field ){
			return '<input type="'. $rg->type .'" id="'. $rg->id .'" name="'. $var->name .'" value="'. esc_attr($rg->val) .'" title="'. esc_attr($rg->title) .'">';
		}

		// text, email, number, url, tel, color, password, date, month, week, range
		function field__default( $rg, $var, $post, $fn__desc, $fn__field ){
			$_style   = ( $rg->type === 'text' && false === strpos($rg->attr, 'style=') ) ? ' style="width:100%;"' : '';

			return $var->title . $fn__field( '<input '. $rg->attr . $var->class  . $var->pholder . $_style .' type="'. $rg->type .'" id="'. $rg->id .'" name="'. $var->name .'" value="'. esc_attr($rg->val) .'">'. $fn__desc() );
		}

		// wp_editor
		function field__wp_editor( $rg, $var, $post, $fn__desc, $fn__field ){
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
			$wp_editor = ob_get_clean();

			return $var->title . $fn__field( $wp_editor . $fn__desc() );
		}

		// image
		function field__image( $rg, $var, $post, $fn__desc, $fn__field ){

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

			if( ! $src = is_numeric($rg->val) ? wp_get_attachment_url( $rg->val ) : $rg->val )
				$src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

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

			return $var->title . $fn__field( $field );
		}

		/**
		 * Сохраняем данные, при сохранении поста
		 * @param  integer $post_id ID записи
		 * @return boolean  false если проверка не пройдена
		 */
		function meta_box_save( $post_id, $post ){

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
						elseif( 'none' === @ $fields_data[$meta_key]['sanitize_func'] ){}
						// не чистим - видимо это произвольная функция вывода полей, которая сохраняет массив
						elseif( is_array($value) ){}
						// нет функции очистки отдельного поля
						else {

							$type = !empty($fields_data[$meta_key]['type']) ? $fields_data[$meta_key]['type'] : 'text';

							if(0){}
							elseif( $type === 'number' )
								$value = floatval( $value );
							elseif( $type === 'email' )
								$value = sanitize_email( $value );
							// wp_editor, textarea
							elseif( in_array( $type, [ 'wp_editor','textarea' ], true ) )
								$value = addslashes( wp_kses( stripslashes( $value ), 'post' ) ); // default ?
							// text, radio, checkbox, color, date, month, tel, time, url
							else
								$value = sanitize_text_field( $value );
						}
					}
					unset($value); // $value используется ниже, поэтому он должен быть пустой, а не ссылкой...

				}
			}

			// Сохраняем
			foreach( $save_metadata as $meta_key => $value ){
				// если есть функция сохранения
				if( !empty($fields_data[$meta_key]['update_func']) && is_callable($fields_data[$meta_key]['update_func']) ){
					call_user_func( $fields_data[$meta_key]['update_func'], $post, $meta_key, $value );
				}
				else {
					// удаляем поле, если значение пустое. 0 остается...
					if( ! $value && ($value !== '0') )
						delete_post_meta( $post_id, $meta_key );
					else
						update_post_meta( $post_id, $meta_key, $value ); // add_post_meta() работает автоматически
				}
			}
		}

		function _postbox_classes_add( $classes ){
			$classes[] = "kama_meta_box_{$this->opt->id}";
			return $classes;
		}

		function _key_prefix(){
			return (substr( $this->opt->id, 0, 1 ) == '_') ? '' : $this->opt->id .'_';
		}

	}

endif;


