# Websocket

A php websocket server.

## Description

Uses "subscriptions"  to pair those connecting to the server. 

Features:
* Ping
	* Sends pong to the client, disables all broadcasts of that message.
* Subscribe
	* Adds the client to a specific subscription group on which to send and recieve messages.
* Queue
	* Adds the client to a queue. The next client added to the queue is then matched with the first client. These two clients are then removed from the queue and put into a subscription group by themselves.
* Party
	* Adds the client to a group which can accept any number of clients. Once a client sends "finalize" the whole party is then moved to a subscription. 

## Getting Started

### Dependencies

* https://getcomposer.org/
* http://socketo.me/

### Installing

```
cd path/to/websocket
composer install
```

### Executing program

* Double click socketStart.bat
```
cd path/to/websocket
socketStart.bat
```
