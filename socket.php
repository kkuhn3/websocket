<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Socket implements MessageComponentInterface {
	protected $clients;
	private $subscriptions;
	private $users;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
		$this->subscriptions = [];
		$this->users = [];
		$this->queues = [];
	}

	public function onOpen(ConnectionInterface $conn) {
		$this->clients->attach($conn);
		$this->users[$conn->resourceId] = $conn;
		$this->subscriptions[$conn->resourceId] = "default";
		echo "New connection! ({$conn->resourceId})\n";
	}

	public function onMessage(ConnectionInterface $conn, $msg) {
		echo "New message from: ({$conn->resourceId}) sent: {$msg}\n";
		$json = json_decode($msg);
		$isJson = $json && $msg != $json;
		if($msg == "ping") {
			$conn->send("pong");
		}
		else if($isJson && isset($json->subscribe)) {
			$this->subscriptions[$conn->resourceId] = $json->subscribe;
		}
		else if($isJson && isset($json->queue)) {
			if(isset($this->queues[$json->queue])) {
				$this->subscriptions[$this->queues[$json->queue]] = "{$json->queue} {$this->queues[$json->queue]} {$conn->resourceId}";
				$this->subscriptions[$conn->resourceId] = "{$json->queue} {$this->queues[$json->queue]} {$conn->resourceId}";
				$this->users[$this->queues[$json->queue]]->send('{"match":1}');
				$conn->send('{"match":2}');
				echo "Matched ({$this->queues[$json->queue]}) with ({$conn->resourceId})\n";
				unset($this->queues[$json->queue]);
			}
			else {
				$this->queues[$json->queue] = $conn->resourceId;
			}
		}
		else {
			$target = $this->subscriptions[$conn->resourceId];
			foreach ($this->subscriptions as $id=>$channel) {
				if ($channel == $target && $id != $conn->resourceId) {
					$this->users[$id]->send($msg);
				}
			}
		}
	}

	public function onClose(ConnectionInterface $conn) {
		$this->clients->detach($conn);
		unset($this->users[$conn->resourceId]);
		unset($this->subscriptions[$conn->resourceId]);
		foreach ($this->queues as $queue=>$savedResourceId) {
			if ($savedResourceId == $conn->resourceId) {
				unset($this->queues[$queue]);
			}
		}
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";
		$conn->close();
	}
}
?>