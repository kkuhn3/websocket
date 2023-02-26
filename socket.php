<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Socket implements MessageComponentInterface {
	protected $clients;
	private $subscriptions;
	private $users;
	private $queues;
	private $parties;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
		$this->subscriptions = [];
		$this->users = [];
		$this->queues = [];
		$this->parties = [];
	}

	public function onOpen(ConnectionInterface $conn) {
		$this->clients->attach($conn);
		$this->users[$conn->resourceId] = $conn;
		$this->subscriptions[$conn->resourceId] = "default";
		$conn->send("{\"resourceId\":\"{$conn->resourceId}\"}");
		echo "Connected ({$conn->resourceId})\n";
	}

	public function onMessage(ConnectionInterface $conn, $msg) {
		echo "New message from: ({$conn->resourceId}) sent: {$msg}\n";
		$json = json_decode($msg);
		$isJson = $json && $msg != $json;
		
		// Pings, send something to the client immediately
		if($msg == "ping") {
			$conn->send("pong");
		}
		
		// subscription, add the client to the set to be subscribed to
		else if($isJson && isset($json->subscribe)) {
			$this->subscriptions[$conn->resourceId] = $json->subscribe;
		}
		
		// queue, if queue already exists, match the 2 clients, otherwise make a new queue
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
				$this->subscriptions[$conn->resourceId] = "{$json->queue} {$conn->resourceId}";
			}
		}
		
		// party, add the user to the party, making a new one if need be
		else if($isJson && isset($json->party)) {
			if(isset($json->finalize)) {
				echo "Party finalized ({$json->party})\n";
				unset($this->parties[$json->party]);
			}
			else {
				if(!isset($this->parties[$json->party])) {
					$this->parties[$json->party] = array();
				}
				$this->parties[$json->party][] = $conn->resourceId;
				echo "Added ({$conn->resourceId}) to the party ({$json->party})\n";
				$partyLeader = reset ($this->parties[$json->party]);
				$this->subscriptions[$conn->resourceId] = "{$json->party} {$partyLeader}";
				$currentParty = json_encode($this->parties[$json->party]);
				foreach ($this->parties[$json->party] as &$id) {
					$this->users[$id]->send("{\"party\":{$currentParty}}");
				}
			}
		}
		
		// else, the user is sending a generic message to be broadcasted to their subscribers
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
		echo "Disconnected ({$conn->resourceId})\n";
		$this->clients->detach($conn);
		unset($this->users[$conn->resourceId]);
		foreach ($this->subscriptions as $user=>$subscription) {
			if($this->subscriptions[$conn->resourceId] == $subscription &&
				$user != $conn->resourceId) {
				$this->users[$user]->send("{\"unsubscribed\":\"{$conn->resourceId}\"}");
			}
		}
		unset($this->subscriptions[$conn->resourceId]);
		foreach ($this->queues as $queue=>$savedResourceId) {
			if ($savedResourceId == $conn->resourceId) {
				unset($this->queues[$queue]);
			}
		}
		foreach ($this->parties as $party=>$aoConnections) {
			if (array_search($conn->resourceId, $aoConnections) !== false) {
				$this->parties[$party] = array_values( array_diff( $aoConnections, [$conn->resourceId] ) );
				$currentParty = json_encode($this->parties[$party]);
				$partyLeader = reset ($this->parties[$party]);
				foreach ($this->parties[$party] as &$id) {
					$this->subscriptions[$id] = "{$party} {$partyLeader}";
					$this->users[$id]->send("{\"party\":{$currentParty}}");
				}
			}
		}
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";
		$conn->close();
	}
}
?>