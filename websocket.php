<?php
	$null = NULL;

	class websocketPHP {
		private $socket_list;
		private $socket;
	
		function __construct($address, $port) {
			$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);   //create a php socket
			socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);  //let the socket be reusable
			socket_set_nonblock($this->socket);                             //dont block if there are no new socket_accept()
			socket_bind($this->socket, $address, $port);                    //where to listen
			socket_listen($this->socket);                                   //start listening
			$this->socket_list = [$this->socket];
			echo "websocketPHP open on $address:$port\r\n\r\n";
	
			while(true) { //listen forever. or until a typo fatally crashes everything.

				//this is the bit that still needs a bit of refactoring:
				//manage multipal connections
				$changed = $clients;
				//returns the socket resources in $changed array
				socket_select($changed, $null, $null, 0, 10);
				
				//check for new socket
				if (in_array($socket, $changed)) {
					$socket_new = socket_accept($socket); //accpet new socket
					$clients[] = $socket_new; //add socket to client array
					
					$header = socket_read($socket_new, 1024); //read data sent by the socket
					perform_handshaking($header, $socket_new, $host, $port); //perform websocket handshake
					
					socket_getpeername($socket_new, $ip); //get ip address of connected socket
					$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //prepare json data
					send_message($response); //notify all users about new connection
					
					//make room for new socket
					$found_socket = array_search($socket, $changed);
					unset($changed[$found_socket]);
				}
				
				//loop through all connected sockets
				foreach ($changed as $changed_socket) {	
					
					//check for any incomming data
					while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
					{
						$received_text = unmask($buf); //unmask data
						$tst_msg = json_decode($received_text, true); //json decode 
						$user_name = $tst_msg['name']; //sender name
						$user_message = $tst_msg['message']; //message text
						$user_color = $tst_msg['color']; //color
						
						//prepare data to be sent to client
						$response_text = mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>$user_message, 'color'=>$user_color)));
						send_message($response_text); //send data
						break 2; //exist this loop
					}
					
					$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
					if ($buf === false) { // check disconnected client
						// remove client for $clients array
						$found_socket = array_search($changed_socket, $clients);
						socket_getpeername($changed_socket, $ip);
						unset($clients[$found_socket]);
						
						//notify all users about disconnected connection
						$response = mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
						send_message($response);
					}
				}

									 
			}
		}
//add new socket to socket list
	function add($socket) {
		socket_accept($socket);
		preg_match('#Sec-WebSocket-Key: (.*)\r\n#', socket_read($socket, 1024), $matches);
		if (count($matches) > 0) {
			$this->send("HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Version: 13\r\n" .
				"Sec-WebSocket-Accept: ".base64_encode(pack("H*",sha1($matches[1]."258EAFA5-E914-47DA-95CA-C5AB0DC85B11")))."\r\n\r\n", [$socket]);

			socket_getpeername($socket, $ip);
			$socket->ip = $ip;
			$socket->uid = uniqid();
			$this->socket_list []= $socket;
			$this->send(["action"=>"connected", "ip"=>$ip], [$socket]);
		}
	}
//decode data recieved
	function decode($data) {
		$l = ord($data[1]) & 127;

		switch (true) {
			case $l == 126: $h = [4, 4]; break;
			case $l == 127: $h = [10, 4]; break;
			default: $h = [2, 4]; break;
		}

		$d = substr($data, $h[0] + $h[1]);
		$m = substr($data, $h[0], $h[1]);

		return implode("", array_map(fn($i) => $d[$i] ^ $m[$i % 4], range(0, strlen($data) - 7)));
	}
//encode data to be sent
	function encode($data) {
		$b = 0x80 | (0x1 & 0x0f);
		$c = gettype($data) === 'string' ? $data : json_encode($data); //connection upgrade / json = string, socket request = object
		$l = strlen($c);

		switch (true) {
			case $l <= 125: $h = pack("CC", $b, $l); break;
			case $l >= 65536: $h = pack("CCNN", $b, 127, $l); break;
			default: $h = pack("CCn", $b, 126, $l); break;
		}

		return $h.$c;
	}
//sends message. $socket_list can be an array of one or more sockets, or empty to broadcast to all connected sockets
    function send($data, $socket_list = null) {
      foreach($socket_list ?? $this->socket_list as $socket) {
        socket_write($socket, $this->encode($data));
      }
    }
	}

	new websocketPHP($argv[1], $argv[2]);
