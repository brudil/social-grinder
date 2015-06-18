social-grinder
==============

A flat-file social activity aggregator. **not actively maintained, but I still love the idea**

### What?
Social Grinder takes your social media streams and grinds it down in to a simple JSON array.

## Features
* Simple JSON response
* Per-account caching
* Supports multiple streams
* Easy to add new services
* JSON based config
* File based, no database needed
* Pwetty URLs
* Excessively commented

## Setting it up

You can have a look at the demo config or:

### Accounts, Services and Streams
#### Services
A **service** is a site such as Twitter, or Github.
Services are found in /services and are just classes that extend `Service`. They're pretty easy to write, and there's a few helper functions to make it easier. Check out the GitHub service to see how it's done.
#### Accounts
You have an **account** on a service, or multiple such as a personal and company Twitter account.
To add account add object to the account object. See below for an example. Each service has different settings, consult the respective class for available properties.
_The comments are for explanation, and is not valid JSON._
```js
"account-name": { // Name of the account
	"service": "twitter", // Service of the account
	"cache": 160, // Time to cache the response, in minutes
	"settings": { // Settings for the service
		"username": "sharmech",
		"include-retweets": true

	}
}
```
#### Streams
**Streams** hold and grind multiple accounts.
You might want a stream that contains Bob's Twitter feed, Flickr feed, and company Twitter feed, and another stream that contains Sally's Twitter feed, Flickr feed, and company Twitter feed.
Streams are added in the streams object. Again, below is an commented example but _only to explain, don't keep any comment in your config because it won't work_.
```js
"personal": { //Name of this stream
	"accounts":[ //Array of accounts to be ground
		"personal-twitter",
		"whs-twitter",
		"personal-github"
	],
	"cors": true, //Wildcard allow-origin
	"count": 15, //Items of activity to be returned
	"client-cache": 1 //Send headers asking for the response to be cached client-side. In minuets, 0 for no caching
}
```
