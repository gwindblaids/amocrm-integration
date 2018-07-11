<?php 
# отправляем заголовок с кодировкой (для читабельного отображения информации о работе скрипта)
header("Content-Type: text/html; charset=utf-8");
class amocrmTask {
	# шаблон массива с параметрами, которые нужно передать для подключения к API
	private $user_connect_data;
	# шаблон массива с данными о пользователе (id, локаль)
	private $user_data;
	# пользовательский поддомен
	private $user_subdomain;
	# шаблон массива со списком сделок
	private $deal_list;
	# шаблон массива с id сделок без открытых задач
	private $empty_deal = array();
	# шаблон массива с данными для создания задачи (func set_task)
	private $set_task = array(
		"add" => array(),
	);
	# в конструкторе реализована логика вызовов функций
	function __construct($user_connect_data, $user_subdomain) {
		# валидация данных
		if (!empty($user_connect_data && !empty($user_subdomain))) {
			$this->user_connect_data = $user_connect_data;
			$this->user_subdomain = $user_subdomain;
			# попытка подключения к API
			echo $this->connect();
			# получаем список сделок
			echo $this->get_list_deal();
			# получаем id сделок без открытых задач
			echo $this->get_id_empty_deal();
			# добавляем новые задачи сделкам без открых задач
			echo $this->set_task();
		}
	}

	private function check_error($code) {
		$code=(int)$code;
		$errors=array(
		  301=>'Moved permanently',
		  400=>'Bad request',
		  401=>'Unauthorized',
		  403=>'Forbidden',
		  404=>'Not found',
		  500=>'Internal server error',
		  502=>'Bad gateway',
		  503=>'Service unavailable'
		);
		try
		{
		  #Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
		 if($code!=200 && $code!=204)
		    throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error',$code);
		}
		catch(Exception $E)
		{
		  die('Ошибка: '.$E->getMessage().PHP_EOL.'Код ошибки: '.$E->getCode());
		}
	}
	private function connect() {
		#Формируем ссылку для запроса
		$link='https://'.strval($this->user_subdomain).'.amocrm.ru/private/api/auth.php?type=json';
		$curl=curl_init(); #Сохраняем дескриптор сеанса cURL
		#Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($this->user_connect_data));
		curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		$out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
		$code=curl_getinfo($curl,CURLINFO_HTTP_CODE); #Получим HTTP-код ответа сервера
		curl_close($curl); #Завершаем сеанс cURL
		# проверяем коды ответа сервера
		$this->check_error($code);
		/*
		 Данные получаем в формате JSON, поэтому, для получения читаемых данных,
		 нам придётся перевести ответ в формат, понятный PHP
		 */
		$Response=json_decode($out,true);
		$Response=$Response['response'];
		# добавляем данные о пользователе в массив user_data
		$this->user_data = $Response['user'];
		if(isset($Response['auth'])) #Флаг авторизации доступен в свойстве "auth"
			return 'Авторизация прошла успешно';
		die ( 'Авторизация не удалась' );
	}
	private function get_list_deal() {
		# формируем url для запроса
		$link = "https://" . strval($this->user_subdomain) . ".amocrm.ru/private/api/v2/json/leads/list";

		$curl=curl_init(); #Сохраняем дескриптор сеанса cURL
		#Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		$out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
		$code=curl_getinfo($curl,CURLINFO_HTTP_CODE); #Получим HTTP-код ответа сервера
		curl_close($curl); #Завершаем сеанс cURL
		# проверяем коды ответа сервера
		$this->check_error($code);

		# разбор полученного json массива со списком сделок
		$Response=json_decode($out,true);
		$this->deal_list=$Response['response'];
	}
	# функция добавляет в массив empty_deal сделки без открытых задач
	private function get_id_empty_deal() {
		# проходим по полученному json и ищем сделки без открытых задач
		foreach ($this->deal_list['leads'] as $key => $value) {
			if ($value['closest_task'] == 0) {

				array_push($this->empty_deal, $value['id']);

			}
		}
	}
	# функция добавляет задачи всем сделкам без открытых задач
	private function set_task() {
		# если есть сделки без открытых задач
		if (!empty($this->empty_deal)) {
			foreach ($this->empty_deal as $key => $value) {
				# добавляем в массив set_task['add'] параметры для создания задачи
				array_push($this->set_task['add'], array(
						   'element_id'=>$value, #ID сделки берем из массива empty_dial
						   'element_type'=>2, #Показываем, что это - сделка, а не контакт
						   'task_type'=>1, #Звонок
						   'text'=>'Сделка без задачи', 
						   'responsible_user_id'=>$this->user_data['id'], # id пользователя полученный при первом запросе к API
						   'complete_till_at'=>1675285346  # крайний срок выполнения задачи
						  ));
			}

		#Формируем ссылку для запроса
		$link='https://'. strval($this->user_subdomain) .'.amocrm.ru/api/v2/tasks';
		$curl=curl_init(); #Сохраняем дескриптор сеанса cURL
		#Устанавливаем необходимые опции для сеанса cURL
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
		curl_setopt($curl,CURLOPT_URL,$link);
		curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
		curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($this->set_task));
		curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
		curl_setopt($curl,CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__).'/cookie.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		$out=curl_exec($curl); #Инициируем запрос к API и сохраняем ответ в переменную
		$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);
		# проверям код ответа сервера
		$this->check_error($code);

		echo "\nЗадачи успешно добавлены!";
		} else die ("\nНет сделок без открытых задач!\n");
	}

	function __destruct() {}
}


$amo_obj = new amocrmTask(
	array("USER_LOGIN" => "gwindblaids@gmail.com",
		  "USER_HASH"  => "69bb3af7b99e9ff5aef677fd30cabe728b59f032",
		  ), "gwindblaids" );

