# Websocket

A php websocket server.

## Description

Uses "subscriptions" and "queues" to pair those connecting to the server. The server has a rudimentary 1v1 matchmaking and allows clients to limit messages they recieve. Has a ping-pong feature. Otherwise the websocket server works like a simple one to many broadcast server.

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
