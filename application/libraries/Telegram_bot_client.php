<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_client
{
	protected $base_url = 'https://api.telegram.org/bot';
	protected $timeout = 15;

	public function get_me($token)
	{
		return $this->request($token, 'getMe');
	}

	public function get_updates($token)
	{
		return $this->request($token, 'getUpdates', array(
			'limit' => 100,
			'allowed_updates' => json_encode(array(
				'message',
				'edited_message',
				'channel_post',
				'edited_channel_post',
				'my_chat_member',
				'chat_member',
			)),
		));
	}

	public function send_message($token, $chat_id, $message)
	{
		return $this->request($token, 'sendMessage', array(
			'chat_id' => $chat_id,
			'text' => $message,
			'disable_web_page_preview' => TRUE,
		), 'POST');
	}

	protected function request($token, $method, $params = array(), $http_method = 'GET')
	{
		$token = trim((string) $token);

		if ( ! $this->valid_token_format($token))
		{
			return $this->response(FALSE, NULL, 'Format Bot Token tidak valid.');
		}

		$url = $this->base_url.$token.'/'.$method;
		$raw = function_exists('curl_init')
			? $this->curl_request($url, $params, $http_method)
			: $this->stream_request($url, $params, $http_method);

		if ( ! $raw['ok'])
		{
			return $this->response(FALSE, NULL, $raw['message'], isset($raw['status']) ? $raw['status'] : NULL);
		}

		$decoded = json_decode($raw['body'], TRUE);

		if ( ! is_array($decoded))
		{
			return $this->response(FALSE, NULL, 'Response Telegram tidak valid.', isset($raw['status']) ? $raw['status'] : NULL);
		}

		if (empty($decoded['ok']))
		{
			$message = isset($decoded['description']) ? $decoded['description'] : 'Telegram API mengembalikan error.';

			if (isset($decoded['error_code']))
			{
				$message = 'Telegram API '.$decoded['error_code'].': '.$message;
			}

			return $this->response(FALSE, NULL, $message, isset($decoded['error_code']) ? (int) $decoded['error_code'] : NULL);
		}

		return $this->response(TRUE, isset($decoded['result']) ? $decoded['result'] : NULL, 'OK', isset($raw['status']) ? $raw['status'] : NULL);
	}

	protected function curl_request($url, $params, $http_method)
	{
		$ch = curl_init();
		$query = http_build_query($params);

		if ($http_method === 'GET' && $query !== '')
		{
			$url .= '?'.$query;
		}

		curl_setopt_array($ch, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_CONNECTTIMEOUT => $this->timeout,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_SSL_VERIFYPEER => TRUE,
			CURLOPT_SSL_VERIFYHOST => 2,
		));

		if ($http_method === 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}

		$body = curl_exec($ch);
		$error = curl_error($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if ($body === FALSE)
		{
			return array('ok' => FALSE, 'message' => 'cURL error: '.$error, 'status' => $status);
		}

		return array('ok' => TRUE, 'body' => $body, 'status' => $status);
	}

	protected function stream_request($url, $params, $http_method)
	{
		$options = array(
			'http' => array(
				'method' => $http_method,
				'timeout' => $this->timeout,
				'ignore_errors' => TRUE,
			),
		);

		if ($http_method === 'GET' && ! empty($params))
		{
			$url .= '?'.http_build_query($params);
		}

		if ($http_method === 'POST')
		{
			$options['http']['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
			$options['http']['content'] = http_build_query($params);
		}

		$body = @file_get_contents($url, FALSE, stream_context_create($options));

		if ($body === FALSE)
		{
			return array('ok' => FALSE, 'message' => 'Tidak bisa menghubungi Telegram API.');
		}

		return array('ok' => TRUE, 'body' => $body, 'status' => 200);
	}

	protected function valid_token_format($token)
	{
		return (bool) preg_match('/^[0-9]{5,}:[A-Za-z0-9_-]{20,}$/', $token);
	}

	protected function response($ok, $result = NULL, $message = '', $status = NULL)
	{
		return array(
			'ok' => (bool) $ok,
			'result' => $result,
			'message' => $message,
			'status' => $status,
		);
	}
}
