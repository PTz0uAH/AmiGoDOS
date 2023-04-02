<?php
 $usermode = "SERIAL_MODE_AUX";
 $server_name = $_ENV['SERVER_NAME'];
 $prompt = '0.SYS:> ';
?>
<!DOCTYPE html>
<html>
<head>
<script src="../js/jquery.js"></script>
<script src="../js/jquery.terminal.min.js"></script>
<link href="../css/jquery.terminal.min.css" rel="stylesheet"/>
<!-- vAmigaWeb-block start -->
<script src="js/vAmigaWeb_player.js"></script>
<script src="../js/keyboard.js"></script>
<script>
var term;
const MODE_AUX = 0;
const MODE_MIDI_MONITOR = 1;
const MODE_AUX_CONSOLE = 2;
const MODE_MIDI_RUNTIME = 3;
const MODE_RAW = 4;
//var CURRENT_MODE = MODE_RAW;
var CURRENT_MODE = MODE_AUX_CONSOLE;
const help = [];
const user_mode = [];
user_mode.push(
 "<?php echo $usermode;?>" ,
 "SERIAL_MODE_MIDI_MONITOR",
 "SERIAL_MODE_AUX_CONSOLE",
 "SERIAL_MODE_MIDI_RUNTIME",
 "SERIAL_MODE_RAW"
);
//the following lines of code demonstrate how to receive AUX: serial data from the Amiga
//formatted in a compact way
let out_buffer="";
let count=0;
window.addEventListener('message', event => {
if(event.data.msg == 'serial_port_out')
{
 switch (CURRENT_MODE) {
 case MODE_AUX, MODE_AUX_CONSOLE:
  let byte_from_amiga=event.data.value;
  out_buffer+=String.fromCharCode( byte_from_amiga & 0xff );
  switch (byte_from_amiga &0xff){
  case 0x0a:
   term.echo(out_buffer.trim());
   out_buffer="";
   break;
  }
  if (out_buffer.includes(": ")==true){
   term.echo(out_buffer.trim());
   out_buffer="";
  }
  if (out_buffer.includes("> ")==true){
   term.set_prompt(out_buffer.trim());
   //term.echo(out_buffer, {newline: false});
   out_buffer="";
  }
  if (out_buffer.includes("Process ")==true){
   if (out_buffer.includes(" ending")==true){
    term.set_prompt("> ");
    //term.echo(out_buffer, {newline: false});
    out_buffer="";
   }
  }
  break;
 case MODE_MIDI_MONITOR:
  let midi_byte_from_amiga=event.data.value & 0xff ;
  switch ( midi_byte_from_amiga ){
  case 0xF8: break; //filter midiclocks
  default:
   count = count +1;
   if (count<31){
   term.echo( " 0x"+midi_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0), {newline: false} );
   }else{
    term.echo( " 0x"+midi_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0) );
    count = 0;
   }
//   term.echo( "0x"+midi_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0) );
   break;
  }
//  term.echo(performance.now()+": "+ midi_byte_from_amiga.toString(16));
  break;
 case MODE_RAW:
  let raw_byte_from_amiga=event.data.value & 0xff ;
  switch ( raw_byte_from_amiga ){
  default:
   count = count +1;
   if (count<31){
   term.echo( " 0x"+raw_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0), {newline: false} );
   }else{
    term.echo( " 0x"+raw_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0) );
    count = 0;
   }
//   term.echo( "0x"+midi_byte_from_amiga.toString(16).toUpperCase().padStart(2, 0) );
   break;
  }
//  term.echo(performance.now()+": "+ midi_byte_from_amiga.toString(16));
  break;
 }
}
});
function ADOS_TX_LINE(msg){
 //get the vAmigaWeb iFrame window
 let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
 let data = msg;
 switch (CURRENT_MODE) {
 case MODE_AUX_CONSOLE:
  //data that should be written into the serial port needs a $0C or \r
  data = data + "\r";
  break;
 }
 //send the data to the serial port of vAmigaWeb
 vAmigaWeb_window.postMessage({cmd:"ser:", text: data}, "*");
}
function ADOS_TX_CHAR(msg){
 // conver msg_key to data_byte
 //get the vAmigaWeb iFrame window
 let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
 if (msg == "_BREAK_"){
  let _BREAK_=0x03;
  let data = String.fromCharCode(_BREAK_);
  //send the data to the serial port of vAmigaWeb
  vAmigaWeb_window.postMessage({cmd:"ser:", text: data}, "*");
 }
}
</script>
<style>
.terminal,span,.cmd,div{--background: silver; --color:rgba(45,45,45,0.99); --font: NewTopaz;}
.terminal,span{--size: 1.0;}
/*Fonts*/
@font-face{
  font-family: NewTopaz;
  src: url("../fonts/Topaz_a1200_v2.0.ttf");
}
body {
background-color: darkgray;
color: white;
}
#vAmigaWeb {
 border:none;
}
#player_container div {
display: none !important;
}
</style>
</head>
<body>
<div  style="display: flex;align-items: center;justify-content: left;">
<div id="container2">
<script>
jQuery( function($){
 var id = 1;
 term = $('body').terminal(
  function(command, term) {
   const command_arr = command.split(" ");
   let cmd = command_arr[0];
   if (cmd!=''){
switch(command_arr.length){
case 1:
 if (cmd == 'help') { term.echo("Available commands:\n alias, assign, cls, echo, exit, help, loadwb, prompt,\n clear, click, close, engage, logout, mode\n ftp(dummy)"); }
 else if (cmd == 'cls') { term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
 else if (cmd == 'alias'){ term.echo("WIP: make short version of long commands/args"); }
 else if (cmd == 'assign'){
  switch (CURRENT_MODE){
  case MODE_MIDI_MONITOR:
   term.echo("WIP: assign i.e. 0xFA midi byte to trigger function in the webpage");
   break;
  case MODE_AUX, MODE_AUX_CONSOLE:
   ADOS_TX_LINE(cmd);
   break;
  default:
   term.echo(cmd + ': Unknown command for chosen MODE');
   break;
  }
 }
 else if (cmd == 'prompt'){ term.echo("<?php echo "$prompt";?>"); }
 else if (cmd == 'about1'){ term.echo("AmiGoDOS dialect is my amiga-ish syntax flavoured devshell originated in Delphi7 Pascal in 2002..");}
 else if (cmd == 'about2'){ term.echo("tried to keep the AmigaDOS syntax somehow alive.. to get things done on MS side.. even in Amiga GUI style");}
 else if (cmd == 'about3'){ term.echo("used to connect to Amiga (FS-UAE) via TCP-COMPORT and relay to MIDI or REMOTE-CONSOLE..");}
 else if (cmd == 'about4'){ term.echo("now using it to connect to vAmigaWeb..AUX/SER/MIDI for Classic Amiga Web usage and using the experience..");}
 else if (cmd == 'about5'){ term.echo("to get it also working for TAWS in the end.. on behalf of the ultimate Amiga Anywhere experience IYKWIM..");}
 else if (cmd == 'about6'){ term.echo("AmiGoDOS for the Web uses JavaScript JQueryTerminal as versatile framework.. with AmiGoDOS syntax/functions..");}
 else if (cmd == 'about7'){ term.echo("a for Web usage simplified AmiGoDOS is just a nice nostalgic label for WIP to keep the momentum going..");}
 else if (cmd == 'about8'){ term.echo("with kind regards.. PTz(Peter Slootbeek)uAH");}
 else if (cmd == 'close'){ parent.close(); }
 else if (cmd == 'exit'){ parent.location.assign("../home.php"); }
 else if (cmd == 'click'){ parent.newcli(); }
 else if (cmd == 'loadwb'){ parent.location.assign("../taws.php"); }
 else if (cmd == 'logout'){ parent.location.assign("../access/logout.php"); }
 else if (cmd == 'ftp'){
term.push(
 function(command, term) {
  if (command == 'help') {term.echo('Available FTP (dummy) commands: ping');}
   else if (command == 'ping') {term.echo('pong');}
   else if (command == 'exit') {term.pop();}
 else {
   term.echo('unknown FTP command ' + command);
  }
 },
 {
   prompt: 'FTP> ',
   name: 'ftp'
 }
);
 }
 else {
  switch (CURRENT_MODE){
  case MODE_AUX, MODE_AUX_CONSOLE:
   ADOS_TX_LINE(cmd);
   //term.echo( cmd +': Unknown command! Type [help] for more info..');
   break;
  default:
   term.echo(cmd + ': Unknown command for chosen MODE');
   break;
  }
//  term.echo( cmd +': Unknown command');
 }
 break;
default:
 if (command_arr.length>>1){ command_arr.splice(0,1);}
 if (cmd == 'echo')   { term.echo( command_arr.join(" ") ); }
 else if (cmd == 'tx'){ ADOS_TX_LINE( command_arr.join(" ") ); }
 else if (cmd == 'click'){ parent.newcli(command_arr[0]); }
 else if (cmd == 'mode') { CURRENT_MODE=Number(command_arr[0]); term.echo("CURRENT_MODE: " + user_mode[CURRENT_MODE] ); }
 else if (cmd == 'exit'){ parent.location.assign(command_arr[0]); }
 else {
  switch (CURRENT_MODE){
  case MODE_AUX, MODE_AUX_CONSOLE:
   ADOS_TX_LINE(command);
   //term.echo( cmd +': Unknown command! Type [help] for more info..');
   break;
  default:
   term.echo(command + ': Unknown command for chosen MODE');
   break;
  }
 }
}
   }
  },
  {
   keymap: {
    "CTRL+C": function() {ADOS_TX_CHAR("_BREAK_"); return false; }
   },
   width: 960,
   height: 320,
   greetings: "AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]",
   prompt: "> ",  // we get the prompt from the amiga aux console
   onBlur: function() { // prevent loosing focus
	return false;
   }
  }
 );
});
</script>
</div>
</div>

<div  style="display: flex;align-items: center;justify-content: left;">
<div id="container">
<img style="width:960px; height:633px" src="../img/C1084.gif"
ontouchstart="touched=true"
onclick="
vAmigaWeb_player.vAmigaWeb_url='./';  //the emulator files are in the same folder as the run.html
let touch=(typeof touched!='undefined')?touched:false;touched=false;
let config={
touch:touch,
AROS:false,
x_wait_for_kickstart_injection:true,
navbar:false,   //you can also enable this and disable the players toolbar (see styles section above)
wide:true,
border:20.20,
port2:false,
//url:'https://github.com/PTz0uAH/AmiGoDOS/raw/main/Ahoy!.ADF',
kickstart_rom_url:'./roms/kick31.rom'
};
vAmigaWeb_player.load(this,encodeURIComponent(JSON.stringify(config)));
return false;"
/>
</div>
</div>
<!--
<div  style="display: flex;align-items: right;justify-content: right;">
<div id="container3">
<img style="position: absolute; left:960px; top:0px; width:960px; height:633px; z-index:10" src="../img/C1084.gif"
ontouchstart="touched=true"
onclick="
vAmigaWeb_player.wasm_write_string_to_ser('echo dit is een test\r');
return false;
"
 >
</div>
</div>-->
</body>
</html>

