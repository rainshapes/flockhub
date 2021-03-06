<?php
include(app_path().'/../vendor/abraham/twitteroauth/twitteroauth/twitteroauth.php');
require_once(app_path() . '/config/oauth.php');

session_start(); // bad, but using until db is going

class AuthController extends BaseController{

	public function login()
	{
		return View::make('Auth.login');
	}
	public function handleLogin(){
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
		$temporary_credentials = $connection->getRequestToken(OAUTH_CALLBACK);
		$_SESSION['temp_credentials'] = $temporary_credentials;
		$redirect_url = $connection->getAuthorizeURL($temporary_credentials); // Use Sign in with Twitter
		return Redirect::to($redirect_url);
	}
	public function callback(){
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['temp_credentials']['oauth_token'], $_SESSION['temp_credentials']['oauth_token_secret']);
		$token_credentials = $connection->getAccessToken($_GET['oauth_verifier']);
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $token_credentials['oauth_token'],
		$token_credentials['oauth_token_secret']);
		$isset_user = User::where('oauth_token', '=', $token_credentials['oauth_token'])->first();
		if(is_null($isset_user)){
			$user = new User;
			$user->oauth_token = $token_credentials['oauth_token'];
			$user->oauth_token_secret = $token_credentials['oauth_token_secret'];
			$user->save();
		} else {
			$user = $isset_user;
		}
		$connection->host = "https://api.twitter.com/1.1/";
		$account_info = $connection->get('account/verify_credentials');
		$user->profile_img = $account_info->profile_image_url_https;
		$user->cover_img = $account_info->profile_banner_url;
		$user->followers = $account_info->followers_count;
		$user->following = $account_info->friends_count;
		$user->twitter_id = $account_info->id;
		$user->handle = $account_info->screen_name;
		$user->name = $account_info->name;
		$user->save();
		$analyze = AnalyzeFollower::where('user_id', '=', $user->id)->first();
		if(is_null($analyze)){
			$analyze = new AnalyzeFollower;
			$analyze->user_id = $user->id;
			$analyze->followers = $user->followers;
		}
		$analyze->numerical_change = $user->followers - $analyze->followers;
		$analyze->percent_change = '%'. ($analyze->numerical_change % $user->followers) * $user->followers;
		$analyze->followers = $user->followers;
		$analyze->save();
		$user = User::where('twitter_id', '=', $account_info->id)->first();
		$user = User::find($user->id);
		Auth::login($user);
		if(Auth::check()){
			return Redirect::action('DashboardController@index');
		} else{
			return 'error';
		}
	}
	public function tweet()
	{		
		$user = Auth::user();
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $user->oauth_token, $user->oauth_token_secret);
		$connection->host = "https://api.twitter.com/1.1/";
		$status = Input::get('tweet');
		$tweet = $connection->post('statuses/update', array('status' => $status));
		$localTweet = new Tweet;
		$localTweet->twitter_id = $tweet->id_str;
		$localTweet->status = $status;
		$localTweet->save();
	 	return Redirect::action('DashboardController@index');		
	}

}



