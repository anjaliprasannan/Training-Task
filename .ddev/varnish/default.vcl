vcl 4.0;

acl purge {
    "localhost";
    "127.0.0.1";
}

backend default {
    .host = "web";
    .port = "80";
}

sub vcl_recv {
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return(synth(405, "Not Allowed"));
        }
        ban("req.http.host == " + req.http.host + " && req.url == " + req.url);
        return(synth(200, "Ban added"));
    }
}

sub vcl_backend_response {
    set beresp.ttl = 10m;
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}