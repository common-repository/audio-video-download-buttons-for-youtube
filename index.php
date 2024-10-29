<?php
/*
 * Plugin Name:     Download buttons for Youtube videos
 * Description:     Read the <a href="https://wordpress.org/plugins/audio-video-download-buttons-for-youtube/">official readme</a> of this plugin.
 * Text Domain:     audio-video-download-buttons-for-youtube
 * Domain Path:     /languages
 * Version:         1.15
 * WordPress URI:   https://wordpress.org/plugins/audio-video-download-buttons-for-youtube/
 * Plugin URI:      https://puvox.software/software/wordpress-plugins/?plugin=audio-video-download-buttons-for-youtube
 * Contributors:    puvoxsoftware,ttodua
 * Author:          Puvox.software
 * Author URI:      https://puvox.software/
 * Donate Link:     https://paypal.me/Puvox
 * License:         GPL-3.0
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 
 * @copyright:      Puvox.software
*/


namespace DownloadButtonsForYoutube
{
  if (!defined('ABSPATH')) exit;
  require_once( __DIR__."/library.php" );
  require_once( __DIR__."/library_wp.php" );
  
  class PluginClass extends \Puvox\wp_plugin
  {

	public function declare_settings()
	{
		$this->initial_static_options	= 
		[
			'has_pro_version'        => 0, 
            'show_opts'              => true, 
            'show_rating_message'    => true, 
            'show_donation_footer'   => true, 
            'show_donation_popup'    => true, 
            'menu_pages'             => [
                'first' =>[
                    'title'           => 'Youtube download buttons', 
                    'default_managed' => 'network',            // network | singlesite
                    'required_role'   => 'install_plugins',
                    'level'           => 'submenu', 
                    'page_title'      => 'Download buttons for Youtube videos',
                    'tabs'            => [],
                ],
            ]
		];

		$this->initial_user_options		= 
		[
			'insert_at_post_start'  => true,
			'custom_field_name'     => 'my_yt_custom_field',
			'custom_field_text'     => '⇩ Download video & audio',
			'custom_field_color'    => '#000000',
			'custom_field_minutes'  => 360,
			'show_video_downloads_too'  => false,
			//
			'ytdlp_local_FILEPATH'			=> '/var/ytdlp_folder/yt-dlp_linux',
			'use_remote_proxy'       		=> false,
			'ytdlp_remote_server_IP'  		=> '',
			'ytdlp_remote_server_PORT'		=> 22,
			'ytdlp_remote_server_USER'  	=> 'ytdlp_user',
			'ytdlp_remote_server_PASSWORD'	=> '',
			'ytdlp_remote_FILEDIR'			=> '/var/ytdlp_folder/',
		];

		$this->shortcodes	=[
			$this->shortcode_name1 =>[
				'description'=>__('Output the breadcrumbs in any place.', 'audio-video-download-buttons-for-youtube'),
				'atts'=>[ 
					['id',		'',			__('Youtube video ID [11 chars] (link is also accepted)',	'audio-video-download-buttons-for-youtube') ],
					['text',	'Download',	__('Text for button', 			'audio-video-download-buttons-for-youtube') ],
					['minutes',	'600',		__('Expire download links in X minutes (So, after X minutes is gone from initial page load, user will need to refresh page again to get fresh links from download-button. This is good to avoid abuse of download by bots or whatever)', 'audio-video-download-buttons-for-youtube') ],
				]
			] 
		];
		
		$this->hooks_examples = [
			"youtube_download_button_shortcode"	=> [ 
				'description'	=>__('Modify output of the shortcode', 'audio-video-download-buttons-for-youtube'), 
				'parameters'	=>['result','atts'],
				'type'			=>'filter'
			],
		];
		
		$this->transition_prefix ='dbfy_trans_'. date('d') . "_";  //sanitize_key($this->helpers->ip)  -will create too many
		$this->transition_video_prefix ='dbfy_videovars_'. date('d') . "_";  //sanitize_key($this->helpers->ip)  -will create too many
	}

	public $shortcode_name1='youtube_download_button';  

	public function __construct_my()
	{
		$this->check_video_request();
		add_filter('the_content',	[$this, 'the_content_filter']);
		$this->helpers->register_stylescript('wp', 'style', 'dbfy_styles', 'assets/styles.css');
		$this->helpers->register_stylescript('wp', 'script', 'dbfy_styles', 'assets/scripts.js');

		$this->message_warn = __('Downloading from youtube is generally a forbidden action (unless you have rights to do so, as defined in their <a href="https://www.youtube.com/static?template=terms" target="_blank">Terms Of Service</a> or <a href="https://www.google.com/search?q=is+it+legal+to+download+youtube+video" target="_blank">find out more</a>). So, ensure you & your users only download videos where you have a right to do so. This plugin is intended to be used only by a very narrow-range of customers, who are allowed to download their targeted videos.');
	}

	// =============================================================================== //
	// =============================================================================== //


	public function youtube_download_button($atts, $content=false)
	{
		$args = $this->helpers->shortcode_atts( $this->shortcode_name1, $this->shortcodes[$this->shortcode_name1]['atts'], $atts);
		return $this->shortcodeWrapper1($args);
	}

	
	public function validId($id){ return strlen($id)==11; }
	
	// ['id'=> 'abcdefghijkl', 'minutes'=>15, 'text'=>'download' ]
	public function shortcodeWrapper1($args=[]) 
	{
		$id = $args['id'];
		if ( stripos($id, '//')!==false)
		{
			$id = $this->helpers->get_youtube_id_from_url($id);
			if ( !$this->validId($id) ){
				$id = $this->helpers->get_youtube_id_from_contents($id);
			}
		}
		$id = sanitize_file_name( $id );
		if ( $this->validId($id) )
		{
			$text = $args['text'];
			set_transient( $this->transition_prefix . $id, true, ((int) $args['minutes']) * MINUTE_IN_SECONDS );
		}
		else{
			$text = "VIDEO ID NOT FOUND";
		}
		$res = 
		'<div class="dbfy-download-wrapper">
			<a class="downloadButton" style="background: '. $this->opts['custom_field_color'] . '" href="#" onclick="javascript:dbfy_download(event, \''.$id.'\');">' . $text . '</a>
			<div class="downloadButtons"></div>
		</div>';
		return apply_filters( 'youtube_download_button_shortcode', $res, $args );
	} 


	// ########################################################## //
	public function check_video_request()
	{
		$post = $this->helpers->POST();
		if (isset($post['dbfy_download']))
		{
		  try
		  {
			$id = sanitize_file_name( $post['dbfy_download'] );
			$res = ['error'=>true, 'data'=>'unknown'];

			// if this video-id was really allowed by this website
			if ( get_transient( $this->transition_prefix . $id ) )
			{
				$key = $this->transition_video_prefix . $id . "_urls";
				$formats = null;
				$transient_value = get_transient($key);
				if ( !$transient_value )
				{
					$res = $this->get_data_for_videoid($id);
					if (!$res['error'])
					{
						$formats = $res['response']['formats'];
						set_transient($key, $formats, $this->opts['custom_field_minutes'] * 60 );
					}
				}
				else{
					$formats = $transient_value;
				}
				//
				if ($formats)
				{
					$videosArray = [];
					$audiosArray = [];
					// we need only audios (as videos dont have an audio)
					foreach ($formats as $each) {
						//
						// response samples
						//
						// {"format_id":"sb3","format_note":"storyboard","ext":"mhtml","protocol":"mhtml","acodec":"none","vcodec":"none","url":"https:\/\/i.ytimg.com\/sb\/X1234512345\/storyboard3_L0\/default.jpg?sqp=-oaymwENSDfyq4qpAwVwAcABBqLzl_8DBgi63o-mBg==&sigh=rs$AOn4CLAu3hpAhs8QgTW8CqZgpIg6xIph9A","width":48,"height":27,"fps":0.36764705882352944,"rows":10,"columns":10,"fragments":[{"url":"https:\/\/i.ytimg.com\/sb\/X1234512345\/storyboard3_L0\/default.jpg?sqp=-oaymwENSDfyq4qpAwVwAcABBqLzl_8DBgi63o-mBg==&sigh=rs$AOn4CLAu3hpAhs8QgTW8CqZgpIg6xIph9A","duration":272}],"resolution":"48x27","aspect_ratio":1.78,"http_headers":{"User-Agent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/94.0.4606.61 Safari\/537.36","Accept":"text\/html,application\/xhtml+xml,application\/xml;q=0.9,*\/*;q=0.8","Accept-Language":"en-us,en;q=0.5","Sec-Fetch-Mode":"navigate"},"audio_ext":"none","video_ext":"none","vbr":0,"abr":0,"tbr":null,"format":"sb3 - 48x27 (storyboard)"}
						//
						// or
						//
						//
						$video_ext = $this->helpers->array_value($each, 'video_ext', 'none');
						$audio_ext = $this->helpers->array_value($each, 'audio_ext', 'none');
						if ($audio_ext === 'none') {
							continue; // we don't want non-audio files in this plugin
						}
						$filesizeRaw = $this->helpers->array_value($each, 'filesize');
						if (!$filesizeRaw) {
							continue;
						}
						$item = [
							'url' => $this->helpers->array_value($each, 'url'),
							'ext' => $audio_ext,
							'quality' => $this->helpers->array_value($each, 'format_note'),
							'filesize' => $filesizeRaw / (1024 * 1024),
							'fps' => $this->helpers->array_value($each, 'fps'),
							'audio_rate' => $this->helpers->array_value($each, 'asr')
						];
						if ($video_ext !== 'none') {
							$videosArray[] = $item;
						}
						else if ($audio_ext !== 'none') {
							$audiosArray[] = $item;
						}
					}
					$final_data = ['audios' => $audiosArray];
					if ($this->opts['show_video_downloads_too']) {
						$final_data['videos'] = $videosArray;
					}
					$res = ['error'=>false, 'data'=>$final_data];
				} else {
					// $res = $res; // would have same error
				}
			}
			else{
				$res = ['error'=>true, 'data'=>'Please, refresh page and click download again.'];
			}
		  } catch (\Exception $ex) {
			$res = ['error'=>true, 'data'=>$ex->getMessage() ];
		  }
		  exit ( json_encode($res) );
		}
	}


	public function get_data_for_videoid($video_id) {
		if ($this->opts['use_remote_proxy']) {
			return $this->get_videodata_remote ($video_id);
		} else {
			return $this->get_videodata_local ($video_id);
		}
	}

	public function get_videodata_local ($video_id) {
		if (!file_exists($this->opts['ytdlp_local_FILEPATH'])) {
			return ['error'=>true, 'response'=>'yt-dlp not found on this server'];
		}
		$res = shell_exec('cd '. $this->opts['ytdlp_remote_FILEDIR'] .'; ./yt-dlp_linux -j ' . $video_id ) ;
		return ['error'=>false, 'response'=>json_decode($res, true)];
	}

	public function get_videodata_remote ($video_id) {
		return $this->get_data_from_remote (
			$video_id,
			$this->opts['ytdlp_remote_server_IP'],
			$this->opts['ytdlp_remote_server_PORT'],
			$this->opts['ytdlp_remote_server_USER'],
			$this->opts['ytdlp_remote_server_PASSWORD'],
		);
	}

	public function get_data_from_remote($video_id, $remote_ssh_IP, $remote_ssh_PORT, $remote_ssh_USERNAME, $remote_ssh_PASSWORD) {
		try {
			$dir = __DIR__;
			$seclib_ver = 'phpseclib1.0.22';
			$seclib_fold = $dir . "/$seclib_ver/";
			if (!is_dir($seclib_fold)) {
				$this->helpers->unzip($dir . "/$seclib_ver.zip",  $seclib_fold);
			}
			// $old_include_path = set_include_path($seclib_fold);  // get_include_path() . PATH_SEPARATOR .  // var_dump(get_include_path())
			include $dir."/$seclib_ver/Net/SSH2.php";  // inlcuded "set_include_path(__DIR__.'/../');" in that php file constructor;
			$ssh = new \Net_SSH2($remote_ssh_IP, $remote_ssh_PORT);   // Domain or IP
			if (!$ssh->login($remote_ssh_USERNAME, $remote_ssh_PASSWORD))  exit('Login Failed');
			$ssh->exec('cd '. $this->opts['ytdlp_remote_FILEDIR']);
			$res = $ssh->exec('./yt-dlp_linux -j ' . $video_id);
			return ['error'=>false, 'response'=>json_decode($res, true)];
		} catch (\Exception $ex) {
			// var_dump ($ex);
			return ['error'=>true, 'response'=>$ex->getMessage()];
		}
	}


	// ===================================== VISUAL ===================================== //



	private $fieldName=null;
	public function the_content_filter($content){
		if ( is_single() && isset($GLOBALS['post']) )
		{
			$this->fieldName = trim($this->opts['custom_field_name'] );
			$post =$GLOBALS['post'];
			$id= $post->post_type;
			$custom_fields = get_post_custom($id);
			if (!empty($custom_fields) && array_key_exists($this->fieldName, $custom_fields))
			{
				$urls = $custom_fields[$this->fieldName];
				if (!empty($urls[0])){
					$url = $urls[0];
					$id = $this->helpers->get_youtube_id_from_url($url);
					$res = $this->shortcodeWrapper1(['id'=>$id, 'minutes'=>$this->opts['custom_field_minutes'], 'text'=>$this->opts['custom_field_text'] ]) ;
					$content = $this->opts['insert_at_post_start'] ? $res.$content : $content.$res;
				}
			}
		}
		return $content;
	}

	// =================================== Options page ================================ //
	public function opts_page_output()
	{ 
		$this->settings_page_part("start", 'first');
		?> 
		<style>
		</style>
		
		<?php if ($this->active_tab=="Options") 
		{
			//if form updated
			if( $this->checkSubmission() ) 
			{
				$this->opts['custom_field_name'] = sanitize_key($_POST[ $this->plugin_slug ]['custom_field_name']);
				$this->opts['custom_field_text'] = sanitize_text_field($_POST[ $this->plugin_slug ]['custom_field_text']);
				$this->opts['custom_field_color'] = sanitize_text_field($_POST[ $this->plugin_slug ]['custom_field_color']);
				$this->opts['custom_field_minutes'] = (int)($_POST[ $this->plugin_slug ]['custom_field_minutes']);
				$this->opts['insert_at_post_start'] = !empty($_POST[ $this->plugin_slug ]['insert_at_post_start']);
				$this->opts['show_video_downloads_too'] = !empty($_POST[ $this->plugin_slug ]['show_video_downloads_too']);
				//
				$this->opts['use_remote_proxy'] = !empty($_POST[ $this->plugin_slug ]['use_remote_proxy']);
				$this->opts['ytdlp_local_FILEPATH'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_local_FILEPATH']));
				$this->opts['ytdlp_remote_FILEDIR'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_remote_FILEDIR']));
				$this->opts['ytdlp_remote_server_IP'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_remote_server_IP']));
				$this->opts['ytdlp_remote_server_PORT'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_remote_server_PORT']));
				$this->opts['ytdlp_remote_server_USER'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_remote_server_USER']));
				$this->opts['ytdlp_remote_server_PASSWORD'] = stripslashes(sanitize_text_field($_POST[ $this->plugin_slug ]['ytdlp_remote_server_PASSWORD']));

				$this->update_opts(); 
			}
			?> 

			<form class="mainForm" method="post" action="">
			
			<b><?php echo $this->message_warn;?></b>
			<?php /*
				<tr>
					<td>
						<b><?php _e( 'If your server IP is blocked by Youtube, you can use external IP/SERVER to redirect API calls from there (to disable, leave empty)', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<input type="text" class="large-text" name="<?php echo $this->slug;?>[api_redirect_call_url]" value="<?php echo $this->opts['api_redirect_call_url'];?>" placeholder="http://example.com/my.php?youtube_id=" /> 
						<p> <?php 
						$pastebin_base= 'https://'.($x='paste'.'bin').'.com'; //yes, quite stupid, because of AVs, they detect direct pastebin urls as malware..
						$pastebin_url_A = "$pastebin_base/AtKUqYie";
						$pastebin_url_B = "$pastebin_base/uUCgxBau";
						_e( 'Instructions: for example, on an unblocked server, create <br/>A) If using public-api: file with <a href="'.$pastebin_url_A.'" target="_blank">this content</a>. <br/>B) If using YT-DLP: file with <a href="'.$pastebin_url_B.'" target="_blank">this content</a>.<br/> However, you are responsible for security/secrecy of that file, and the referenced code is just an plain example.', 'audio-video-download-buttons-for-youtube');
						?> 
						</p>
					</td>
				</tr> 
			*/ ?>
			<table class="form-table"><tbody> 
				<tr>
					<td>
						<b><?php _e( 'Local server file path of yt-dlp', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<input type="text" class="normal-text" name="<?php echo $this->slug;?>[ytdlp_local_FILEPATH]" value="<?php echo $this->opts['ytdlp_local_FILEPATH'];?>" placeholder="<?php echo $this->opts['ytdlp_local_FILEPATH'];?>" />  
						<br/>
						<b><?php $js = 'document.getElementById("local_ytdlp_commands").style.display="block";return false;';  _e( "This plugin relies on <b>yt-dlp</b> application. Regular shared hostings might not have that available, but if this server is a VPS, then follow <a href='#' onclick='$js'>run these commands</a>  in terminal to get it (if you have remove VPS available somewhere, see the options in the bottom of page)", 'audio-video-download-buttons-for-youtube'); ?></b>
						<textarea id="local_ytdlp_commands" disabled style="display:none; font-size:11px; overflow:auto; width: 350px; height:120px; resize:none;"><?php echo 
							'export YTLDP_FOLDER=/var/ytdlp_folder'. "\r\n".
							'mkdir $YTLDP_FOLDER'. "\r\n".
							'cd $YTLDP_FOLDER'. "\r\n".
							'wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux'. "\r\n".
							'sudo chmod -R 750 $YTLDP_FOLDER'. "\r\n".
							'sudo chown -R www-data:www-data $YTLDP_FOLDER';
						;?></textarea>
					</td>
				</tr>
				<tr>
					<td colspan=2 style="text-align: center;">
					  Automatically use with custom fields
					</td>
				</tr> 
				<tr>
					<td>
						<b><?php _e( 'Attach to the custom-field:', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<input type="text" class="normal-text" name="<?php echo $this->slug;?>[custom_field_name]" value="<?php echo $this->opts['custom_field_name'];?>" placeholder="my_yt_url" />  
						<p><?php _e( 'This plugin will look for this key-named custom-field in the post and if found, will use its value as youtube video url/id', 'audio-video-download-buttons-for-youtube'); ?></p>
					</td>
				</tr>   
				<tr>
					<td>
						<b><?php _e( 'Text to be used when showing from custom fields'); ?></b>
					</td>
					<td> 
						<input type="text" class="large-small" name="<?php echo $this->slug;?>[custom_field_text]" value="<?php echo $this->opts['custom_field_text'];?>" placeholder="Download" />  
					</td>
				</tr>
				<tr>
					<td>
						<b><?php _e( 'Button color'); ?></b>
					</td>
					<td> 
						<input class="large-small" name="<?php echo $this->slug;?>[custom_field_color]" type="color" value="<?php echo $this->opts['custom_field_color'];?>" placeholder="#E123456" />  
					</td>
				</tr>
				<tr>
					<td colspan=2 style="text-align: center;">
					  other
					</td>
				</tr> 
				<tr>
					<td>
						<b><?php _e( 'How many minutes should the links be cached'); ?></b>
					</td>
					<td> 
						<input type="text" class="large-small" name="<?php echo $this->slug;?>[custom_field_minutes]" value="<?php echo $this->opts['custom_field_minutes'];?>" placeholder="600" />  
					</td>
				</tr>   
				<tr>
					<td>
						<b><?php _e( 'Place download button in the start of the post (otherwise in the bottom)', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<p><input type="checkbox" name="<?php echo $this->slug;?>[insert_at_post_start]" value="1" <?php checked($this->opts['insert_at_post_start'], true);?> /></p>
					</td>
				</tr>

				<tr>
					<td>
						<b><?php _e( 'Show VIDEO downloads too (they might not contain audio, so it might not be needed for users)', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<p><input type="checkbox" name="<?php echo $this->slug;?>[show_video_downloads_too]" value="1" <?php checked($this->opts['show_video_downloads_too'], true);?> /></p>
					</td>
				</tr>

				<tr>
					<td colspan=2 style="text-align: center;">
					  REMOTE/PROXY
					</td>
				</tr> 
				<tr>
					<td>
						<b><?php _e( 'Use external server', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td> 
						<input type="checkbox" name="<?php echo $this->slug;?>[use_remote_proxy]" value="1" <?php checked($this->opts['use_remote_proxy'], true);?> />
						<p><b><?php $js = 'document.getElementById("remote_ytdlp_commands").style.display="block";return false;';  _e( "Instruction: If the current hosting does not have yt-dlp installed, then you can setup an external vps and use it. You can buy some cheapest ubuntu VPS anywhere and (with root user) enter <a href='#' onclick='$js'>these lines</a> in terminal", 'audio-video-download-buttons-for-youtube'); ?></b>
						<span style="color:red"><?php _e( '(change 123456 password !)', 'audio-video-download-buttons-for-youtube'); ?></span>
						<br/>
						<textarea id="remote_ytdlp_commands" disabled style="display:none; font-size:11px; overflow:auto; width: 450px; height:180px; resize:none;"><?php echo 
							'export YTLDP_PASS=\'123456\''. "\r\n".
							'export YTLDP_USER=ytdlp_user'. "\r\n".
							'export YTLDP_FOLDER=/var/ytdlp_folder'. "\r\n".
							'sudo adduser $YTLDP_USER --shell /bin/bash'. "\r\n".
							'echo $YTLDP_PASS | sudo passwd --stdin $YTLDP_USER'. "\r\n".
							'mkdir $YTLDP_FOLDER'. "\r\n".
							'cd $YTLDP_FOLDER'. "\r\n".
							'wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux'. "\r\n".
							'sudo usermod -d $YTLDP_FOLDER $YTLDP_USER'. "\r\n".
							'sudo chmod -R 750 $YTLDP_FOLDER'. "\r\n".
							'sudo chown -R $YTLDP_USER:$YTLDP_USER $YTLDP_FOLDER';
						;?></textarea>
						</p>
					</td>
				</tr>
				<tr>
					<td>
						<b><?php _e( 'Fill remote server details', 'audio-video-download-buttons-for-youtube'); ?></b>
					</td>
					<td>
						IP:<input type="text" class="normal-text" name="<?php echo $this->slug;?>[ytdlp_remote_server_IP]" value="<?php echo $this->opts['ytdlp_remote_server_IP'];?>" placeholder="x.x.x.x" />
						PORT:<input type="text" class="small-text" name="<?php echo $this->slug;?>[ytdlp_remote_server_PORT]" value="<?php echo $this->opts['ytdlp_remote_server_PORT'];?>" placeholder="22" />
						Username:<input type="text" class="small-text" name="<?php echo $this->slug;?>[ytdlp_remote_server_USER]" value="<?php echo $this->opts['ytdlp_remote_server_USER'];?>" placeholder="ytdlp_user" />
						Password:<input type="password" class="small-text" name="<?php echo $this->slug;?>[ytdlp_remote_server_PASSWORD]" value="<?php echo $this->opts['ytdlp_remote_server_PASSWORD'];?>" placeholder="" />
						Directory:<input type="text" class="normal-text" name="<?php echo $this->slug;?>[ytdlp_remote_FILEDIR]" value="<?php echo $this->opts['ytdlp_remote_FILEDIR'];?>" placeholder="" />
						<br/><?php _e('If you do not know, leave default fields as is');?>
					</td>
				</tr>
				
			</tbody></table>

			<?php $this->nonceSubmit(); ?>

			</form>

		<?php 
		} 

		$this->settings_page_part("end", '');
	} 



  } // End Of Class

  $GLOBALS[__NAMESPACE__] = new PluginClass();

} // End Of NameSpace

?>