<p style="text-align:center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>



## Apache Status Helper

I manage some cPanel/WHM servers for hosting, and they get hammered with bot traffic.  One way to deal with that is to look at the Apache `server-status` page, which lists IP addresses & request URIs.

I often see a bunch of POST requests to `wp-login.php`, or requests to strange URIs like `alfa-rex2.php`.  If I do an IP address lookup, those addresses often belong to VPN providers or to Cloud hosting providers.  That's a pretty clear sign of bot traffic, so I can ban the IP address using ConfigServer Firewall (csf).

But that's a lot of steps.
1. Check the `server-status` page and look for IP addresses that repeat.
2. Use a 3rd party website/tool to look up each address.
3. Use my terminal to SSH to the server and run the `csf` command to ban that IP address.
 
### Let's make life easier

I've built this app on Laravel, and you can configure it through .env variables.  It provides a web interface, which checks the `server-status` page of each configured server, and provides a combined list of IP addresses.  It also allows you to paste in a copy/paste from a status page if you want to check some manually.

It performs a lookup of each IP address, noting it's location & network provider.  It also displays a list of requests from each IP address if available.  You can click on an IP address to copy it to clipboard, or tick on a row to select it.

Selected rows will be added to a command box at the bottom, which provides `csf -td` commands for each IP address in a single line that you can copy/paste into your SSh window.

## Setup

Should be as simple as updating the `.env` file.  

Copy the the `.env.example` and update to suit your needs.


## TODO list

* Look at setting this up as a [docker container](https://buddy.works/guides/laravel-in-docker).
* Look at using [Concurrent Requests](https://laravel.com/docs/11.x/http-client#concurrent-requests) to scrape the server pages, intead of one-by-one as I'm currently doing.

