<?php	if( ! defined( 'BILLING_MODULE' ) ) die( "Hacking attempt!" );
/**
 * DLE Billing
 *
 * @link          https://github.com/mr-Evgen/dle-billing-module
 * @author        dle-billing.ru <evgeny.tc@gmail.com>
 * @copyright     Copyright (c) 2012-2017, mr_Evgen
 */

Class ADMIN
{
	var $_Config = array();
	var $local_lang = array();

	function __construct()
	{
		require_once DLEPlugins::Check( MODULE_PATH . "/plugins/payhide/lang.php" );

		$this->local_lang = $plugin_lang;
	}

	function main( $GET )
	{
		# Требуется установка
		#
		if( ! file_exists( MODULE_DATA . "/plugin.payhide.php" ) )
		{
			$this->install();
		}

		$this->plugin_config = include MODULE_DATA . "/plugin.payhide.php";

		# Сохранить настройки
		#
		if( isset( $_POST['save'] ) )
		{
			if( $_POST['user_hash'] == "" or $_POST['user_hash'] != $this->Dashboard->hash )
			{
				return "Hacking attempt! User not found {$_POST['user_hash']}";
			}

			$this->Dashboard->SaveConfig("plugin.payhide", $_POST['save_con']);

			$this->Dashboard->ThemeMsg( $this->Dashboard->lang['ok'], $this->Dashboard->lang['save_settings'] );
		}

		# Удаление
		#
		if( isset( $_POST['act_do'] ) )
		{
			if( $_POST['user_hash'] == "" or $_POST['user_hash'] != $this->Dashboard->hash )
			{
				return "Hacking attempt! User not found {$_POST['user_hash']}";
			}

			$MassList = $_POST['massact_list'];

			foreach( $MassList as $id )
			{
				$id = intval( $id );

				if( ! $id ) continue;

				$this->Dashboard->LQuery->db->super_query( "DELETE FROM " . USERPREFIX . "_billing_payhide WHERE payhide_id='$id'" );
			}

			$this->Dashboard->ThemeMsg( $this->Dashboard->lang['ok'], $this->local_lang['remove'] );
		}

		# Список
		# 
		$this->Dashboard->ThemeAddTR( array(
			'<td width="1%">#</td>',
			'<td>'.$this->Dashboard->lang['history_date'].'</td>',
			'<td>'.$this->local_lang['user'].'</td>',
			'<td>'.$this->local_lang['paypage'].'</td>',
			'<td>'.$this->local_lang['autor'].'</td>',
			'<td>'.$this->local_lang['summa'].'</td>',
			'<td>'.$this->local_lang['time'].'</td>',
			'<td width="2%"><center><input class="icheck" type="checkbox" value="" name="massact_list[]" onclick="checkAll(this);$.uniform.update();" /></center></td>'
		));

		$PerPage = $this->Dashboard->config['paging'];
		$StartFrom = $GET['page'];

		$this->Dashboard->LQuery->parsPage( $StartFrom, $PerPage );

		$ResultCount = $this->Dashboard->LQuery->db->super_query( "SELECT COUNT(*) as `count`
																	FROM " . USERPREFIX . "_billing_payhide
																	ORDER BY payhide_id desc" );

		$this->Dashboard->LQuery->db->query( "SELECT * FROM " . USERPREFIX . "_billing_payhide
												LEFT JOIN " . USERPREFIX . "_post ON " . USERPREFIX . "_billing_payhide.payhide_post_id=" . USERPREFIX . "_post.id
												ORDER BY payhide_id desc LIMIT {$StartFrom}, {$PerPage}" );

		while ( $Value = $this->Dashboard->LQuery->db->get_row() )
		{
			$this->Dashboard->ThemeAddTR( array(
				$Value['payhide_id'],
				$this->Dashboard->ThemeChangeTime( $Value['payhide_date'] ),
				$this->Dashboard->ThemeInfoUser( $Value['payhide_user'] ),
				$Value['payhide_post_id'] ? sprintf( $this->local_lang['access_post'], $Value['payhide_pagelink'], $Value['title'] ) : sprintf( $this->local_lang['access_page'], $Value['payhide_pagelink'] ),
				$Value['autor'] ? $this->Dashboard->ThemeInfoUser( $Value['autor'] ) : '',
				$Value['payhide_price'] . ' ' . $this->Dashboard->API->Declension( $Value['payhide_price'] ),
				$Value['payhide_time'] ? ( ( $Value['payhide_time']>=$this->Dashboard->_TIME ) ? "<font color='green'>".$this->local_lang['timeTo'].langdate( "j F Y  G:i", $Value['payhide_time'])."</font>": "<font color='red'>".$this->local_lang['timeTo'].langdate( "j F Y  G:i", $Value['payhide_time'])."</font>" ) : $this->local_lang['timeFull'],
				"<center><input name=\"massact_list[]\" value=\"".$Value['payhide_id']."\" class=\"icheck\" type=\"checkbox\"></center>"
			));
		}

		$Content = $this->Dashboard->ThemeParserTable();

		if( $ResultCount['count'])
		{
			$Content .= $this->Dashboard->ThemePadded( '
				<div class="pull-left">
					<ul class="pagination pagination-sm">' .
						$this->Dashboard->API->Pagination(
							$ResultCount['count'],
							$GET['page'],
							$PHP_SELF . "?mod=billing&c=payhide&p=page/{p}",
							"<li><a href=\"{page_num_link}\">{page_num}</a></li>",
							"<li class=\"active\"><span>{page_num}</span></li>",
							$PerPage
						) . '</ul>
					</ul>
				</div>

				<div class="pull-right">
					<button class="btn bg-danger btn-sm btn-raised" name="act_do" type="submit"><i class="fa fa-trash-o position-left"></i>'.$this->Dashboard->lang['remove'].'</button>
				</div>
				<input type="hidden" name="user_hash" value="' . $this->Dashboard->hash . '" />', 'box-footer', 'right' );
		}
		else
		{
			$Content .= $this->Dashboard->ThemePadded( $this->Dashboard->lang['history_no'], '' );
		}

		$tabs[] = array(
			'id' => 'list',
			'title' => $this->local_lang['title'],
			'content' => $Content
		);

		$tabs[] = array(
			'id' => 'tag',
			'title' => $this->local_lang['gentags'],
			'content' => $this->tag()
		);

		$tabs[] = array(
			'id' => 'settings',
			'title' => $this->local_lang['settigns'],
			'content' => $this->settings( $this->plugin_config )
		);

		$this->Dashboard->ThemeEchoHeader();

		$Content = $this->Dashboard->PanelPlugin('plugins/payhide', 'icon-cogs', $this->plugin_config['status'], 'https://dle-billing.ru/doc/payhide/info.html' );
		$Content .= $this->Dashboard->PanelTabs( $tabs );
		$Content .= $this->Dashboard->ThemeEchoFoother();

		return $Content;
	}

	# Установка
	#
	private function install()
	{

		$this->Dashboard->SaveConfig("plugin.payhide", array('status'=>"0"));

		return;
	}

	# Форма настроек
	#
	private function settings( $_Config )
	{
		$this->Dashboard->ThemeAddStr(
			$this->Dashboard->lang['settings_status'],
			$this->Dashboard->lang['refund_status_desc'],
			$this->Dashboard->MakeICheck("save_con[status]", $_Config['status'])
		);

		$this->Dashboard->ThemeAddStr(
			$this->Dashboard->lang['paysys_name'],
			$this->Dashboard->lang['refund_name_desc'],
			"<input name=\"save_con[name]\" class=\"form-control\" type=\"text\" value=\"" . $_Config['name'] ."\" style=\"width: 100%\">"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['percent'],
			$this->local_lang['percent_desc'],
			"<input name=\"save_con[percent]\" class=\"form-control\" type=\"text\" value=\"" . $_Config['percent'] ."\" style=\"width: 20%\"> %"
		);

		$Content .= $this->Dashboard->ThemeParserStr();
		$Content .= $this->Dashboard->ThemePadded( $this->Dashboard->MakeButton("save", $this->Dashboard->lang['save'], "green") );

		return $Content;
	}

	# Форма генерации тега
	#
	private function tag()
	{
		$genKey = $this->Dashboard->genCode( 3 );

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_1'],
			$this->local_lang['tag_1d'],
			"<input id=\"phGenFormKey\" onkeyup=\"phGenForm()\" class=\"form-control\" value=\"{$genKey}\" type=\"text\" style=\"width: 20%\">" . $this->local_lang['tag_1d7']
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_6'],
			$this->local_lang['tag_6d'],
			"<input class=\"icheck\" type=\"checkbox\" onchange=\"phGenForm()\" id=\"phGenFormIDnews\" value=\"0\">"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_autor'],
			$this->local_lang['tag_autor_desc'],
			"<input class=\"icheck\" type=\"checkbox\" onchange=\"phGenForm()\" id=\"phGenFormAutor\" value=\"0\">"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_2'],
			$this->local_lang['tag_2d'],
			"<input id=\"phGenFormPrice\" onkeyup=\"phGenForm()\" class=\"form-control\" type=\"text\" size=\"14\" value=\"10.00\" style=\"width: 20%\"> " . $this->Dashboard->API->Declension( 10 )
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_3'],
			$this->local_lang['tag_3d'],
			"<input id=\"phGenFormTime\" onkeyup=\"phGenForm()\" class=\"form-control\" size=\"14\" type=\"text\" style=\"width: 20%\">" . $this->local_lang['key_time']
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_theme'],
			$this->local_lang['tag_theme_desc'],
			"<input id=\"phGenFormTheme\" onkeyup=\"phGenForm()\" class=\"form-control\" style=\"width: 100%\" type=\"text\">"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_theme_open'],
			$this->local_lang['tag_theme_open_desc'],
			"<input id=\"phGenFormThemeOpen\" onkeyup=\"phGenForm()\" class=\"form-control\" style=\"width: 100%\" type=\"text\">"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_4'],
			$this->local_lang['tag_4d'],
			"<select data-placeholder=\"{$this->local_lang['chose_group']}\" title=\"{$this->local_lang['chose_group']}\" id=\"phGenFormGroups\" class=\"group_select\" style=\"width:100%;\" onchange=\"phGenForm()\" multiple><option></option>" . $this->Dashboard->GetGroups() . "</select>"
		);

		$this->Dashboard->ThemeAddStr(
			$this->local_lang['tag_5'],
			$this->local_lang['tag_5d'],
			"<textarea style=\"width:100%;height:60px\" onClick=\"this.focus(); this.select()\" id=\"phGenFormTag\">[payhide key={$genKey} price=10.00]{$this->local_lang['tag_form']}[/payhide]</textarea>"
		);

		$Content .= <<<HTML
		<script type="text/javascript">
			function phGenForm()
			{
				var genForm = '[payhide';

				var arr = [];

				$('#phGenFormGroups option:selected').each(function(index)
				{
					arr[index] = $(this).val();
				});

				if( arr[0] )
				{
					genForm += ' open='+arr.join(',');
				}

				if( ! $("#phGenFormPrice").val() )
				{
					DLEalert('{$this->local_lang['tag_error']}', '{$this->Dashboard->lang['error']}');

					$("#phGenFormPrice").val("10.00");
				}

				if( ! $("#phGenFormKey").val() )
				{
					DLEalert('{$this->local_lang['key_error']}', '{$this->Dashboard->lang['error']}');

					$("#phGenFormKey").val("{$genKey}");
				}

				if( $("#phGenFormAutor").prop("checked") )
				{
					genForm += ' autor=1';
				}

				if( $("#phGenFormIDnews").prop("checked") )
				{
					genForm += ' post=1';
				}

				genForm += ' key=' + $("#phGenFormKey").val();
				genForm += ' price=' + $("#phGenFormPrice").val();

				if( $("#phGenFormTime").val() )
				{
					genForm += ' time=' + $("#phGenFormTime").val();
				}

				if( $("#phGenFormTheme").val() )
				{
					genForm += ' theme=' + $("#phGenFormTheme").val();
				}

				if( $("#phGenFormThemeOpen").val() )
				{
					genForm += ' theme_open=' + $("#phGenFormThemeOpen").val();
				}

				genForm += ']{$this->local_lang['tag_form']}[/payhide]';

				$("#phGenFormTag").val( genForm );
			}
			$(function(){
				$('.group_select').chosen({no_results_text: 'Ничего не найдено'});
			});
		</script>
HTML;

		$Content .= $this->Dashboard->ThemeParserStr();
		$Content .= $this->Dashboard->ThemePadded( "" );

		return $Content;
	}
}
?>
