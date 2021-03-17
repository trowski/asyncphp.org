
var ws = new WebSocket("wss://" + window.location.host + "/broadcast");
var list = document.getElementById("messages");
var input = document.querySelector('input[name=message]');

// append all received messages to #messages
ws.addEventListener("message", function(e) {
    console.log(e.data);

    var listItem = document.createElement('li');
    listItem.className = 'delayed';
    listItem.textContent = e.data;

    list.append(listItem);

    while (list.children.length > 20) {
        list.removeChild(list.firstChild);
    }
});

input.addEventListener('keyup', function (e) {
    if (e.keyCode === 13) {
        e.preventDefault();

        ws.send(e.target.value);
        e.target.value = "";
        e.target.focus();
    }
});
