<script src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>
<script type="text/javascript" src="http://materializecss.com/bin/materialize.js"></script>
<script>
    $(document).ready(function () {
        $('ul.teachers').tabs();
        $(".button-collapse").sideNav();
    });


    function openType(target) {

        console.info(target);

        var form = document.createElement('form'),
            node = document.createElement("input");
        form.method = "post";
        form.style.display = "none";
        form.name = "openLink";
        form.id = "openLink";

        node.type = "text";
        node.name = "type";
        node.value = target;

        form.appendChild(node);
        document.getElementById("body").appendChild(form);
        document.forms['openLink'].submit();
    }

/*
    $('a').click(function (e) {

        if(!e.currentTarget.href.includes("type="))
            return;

        e.preventDefault();
        var target = e.currentTarget.href.split("?")[1].split("=")[1];

        openType(target);


    });
*/
</script>