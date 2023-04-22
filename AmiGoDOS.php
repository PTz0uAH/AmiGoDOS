<?php
 $usermode = "SERIAL_MODE_AUX";
 $server_name = $_ENV['SERVER_NAME'];
 $prompt = '0.SYS:> ';
 if (!isset($_GET['amiga'])){$amiga='Buffy';}else{$amiga=$_GET['amiga'];}
 //behaviour.. if set then autoboot
 if ( !isset($_GET['autoboot']) ){ $init=''; }
 else{ $init='onInit: function() { TS0CA("'.$amiga.'");},'; }
 if ( !isset($_GET['boot']) ){
  $boot = '';
  $snapshot="     buttons: [
   {run: true, script:`global_apptitle='AmiGoXPE Salvation Platform ($amiga)';action('1940ms=>restore_last_snapshot');`}
  ]";
 }else{
  $boot = $_GET['boot'];
  $snapshot="url:'$boot'";
 }
  //the emulator files are in the same folder as the run.html
  //you can also enable this and disable the players toolbar (see styles section above)
$config_1="
    vAmigaWeb_player.vAmigaWeb_url='./';
    let touch=(typeof touched!='undefined')?touched:false;touched=false;
    let config={
     touch:touch,
     AROS:false,
     x_wait_for_kickstart_injection:true,
     navbar:false,
     wide:true,
     border:0.3,
     mouse:true,
     port2:false,
     $snapshot
     //kickstart_rom_url:'./roms/kick31.rom'
    };
    vAmigaWeb_player.load(this,encodeURIComponent(JSON.stringify(config)));
   return false;";
?>
<!DOCTYPE html>
<html>
<head>
<script src="js/jquery.js"></script>
<script src="js/jquery.terminal.min.js"></script>
<link href="css/jquery.terminal.min.css" rel="stylesheet"/>
<script src="js/vAmigaWeb_player.js"></script>
<script src="js/keyboard.js"></script>
<script>
var term;
const server_name = "<?php echo $server_name;?>"
//var PROMPT_TRIGGERS = ["> ","/N ","S/ ","/K "];
const MODE_AUX = 0;
const MODE_MIDI_MONITOR = 1;
const MODE_AUX_CONSOLE = 2;
const MODE_MIDI_RUNTIME = 3;
const MODE_DEBUG = 4;
//var CURRENT_MODE = MODE_DEBUG;
var CURRENT_MODE = MODE_AUX_CONSOLE;
const help = [];
const user_mode = [];
user_mode.push(
 "<?php echo $usermode;?>" ,
 "SERIAL_MODE_MIDI_MONITOR",
 "SERIAL_MODE_AUX_CONSOLE",
 "SERIAL_MODE_MIDI_RUNTIME",
 "SERIAL_MODE_DEBUG"
);
//how to receive AUX: serial data from the Amiga formatted in a compact way (trimmed)
let out_buffer="";
let count=0;
window.addEventListener('message', event => {
if(event.data.msg == 'serial_port_out')
{
 switch (CURRENT_MODE) {
 case MODE_AUX:
 case MODE_AUX_CONSOLE:
  let byte_from_amiga=event.data.value;
  out_buffer+=String.fromCharCode( byte_from_amiga & 0xff );
  switch (byte_from_amiga &0xff){
  case 0x0a:
   term.echo(out_buffer.trim());
   out_buffer="";
   break;
  }
  //AmigaDOS switches for help mode [CMD ?]
  if (out_buffer.includes("/N: ")==true){term.set_prompt(out_buffer.trim());out_buffer="";}
  if (out_buffer.includes("/S: ")==true){term.set_prompt(out_buffer.trim());out_buffer="";}
  if (out_buffer.includes("/K: ")==true){term.set_prompt(out_buffer.trim());out_buffer="";}
  if (out_buffer.includes("> ")==true){term.set_prompt(out_buffer.trim());out_buffer="";}
  //term.echo(out_buffer, {newline: false});
  //let i = 0;
  //check_trigger: while(i < 4)
  //{
  // if (out_buffer.includes(PROMPT_TRIGGERS[i])==true){
  //  term.set_prompt(out_buffer.trim());
  //  out_buffer="";
  //  break check_trigger;
  // }
  //}
  //Check if EndCLI was called
  if (out_buffer.includes("Process ")==true){
   if (out_buffer.includes(" ending")==true){term.set_prompt("> ");out_buffer="";}
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
 case MODE_DEBUG:
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
 let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
 let data = msg;
 switch (CURRENT_MODE) {
 case MODE_AUX_CONSOLE:
 case MODE_DEBUG:
  data = data + "\r";
  break;
 }
 vAmigaWeb_window.postMessage({cmd:"ser:", text: data}, "*");
}
function ADOS_TX_CHAR(msg){
 let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
 if (msg == "_BREAK_"){
  let _BREAK_=0x03;
  let data = String.fromCharCode(_BREAK_);
  vAmigaWeb_window.postMessage({cmd:"ser:", text: data}, "*");
 }
}
// MIDI_RUNTIME
function ADOS_TX_MIDI(msg){
 let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
// if (data != ""){ vAmigaWeb_window.postMessage({cmd:"ser:", text: data}, "*");}

      if (msg == 'start'){ var b = 0xFA;}
 else if (msg == 'cont'){ var b = 0xFB;}
 else if (msg == 'stop'){ var b = 0xFC;}
 switch (b){
 case 0xFA: vAmigaWeb_window.postMessage({cmd:"ser:", byte: b}, "*");break;
 case 0xFB: vAmigaWeb_window.postMessage({cmd:"ser:", byte: b}, "*");break;
 case 0xFC: vAmigaWeb_window.postMessage({cmd:"ser:", byte: b}, "*");break;
 default:
  term.echo("UNASSIGNED_BYTE");
 }
}
// local functions when not connected to AUX:
function ADOS_ALIAS(key, value){

}

function ADOS_ASSIGN(key, value){
}

function TS0CA(modelID='Buffy') {
const modelAmiga = document.getElementById(modelID);
 modelAmiga.click();
}
//function removeDiv(machineID='container')
//{
// var vAmigaWebDiv = document.getElementById(machineID);
// vAmigaWebDiv.parentNode.removeChild();
//}
</script>
<style>
.terminal,span,.cmd,div{
--background: silver;
--color:rgba(45,45,45,0.99);
--font: NewTopaz;
}
.terminal,span{--size: 1.0;}
@font-face{
font-family: NewTopaz;
src: url("fonts/Topaz_a1200_v2.0.ttf");
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
<div id="AmiGoDOS" style="display: flex;align-items: center;justify-content: left;">
<div id="NewShell">
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
     if (cmd == 'help') { term.echo("AmiGoDOS commands:\n"+
     " alias, assign, cls, echo, exit, help, prompt,\n"+
     " clear, click, close, engage, logout, mode\n"+
     "Available Amiga's by Name: amy, buffy, claire, daisy, eva, faith, gwen\n"+
     "Available Amiga's by Type: a500, a600, a1000, a2000, a3000, cdtv\n"+
     "Command Shells: midi, vamiga, ftp(dummy)"); }
     else if (cmd == 'about1'){ term.echo("AmiGoDOS dialect is my amiga-ish syntax flavoured devshell originated in Delphi7 Pascal in 2002..");}
      else if (cmd == 'about2'){ term.echo("tried to keep the AmigaDOS syntax somehow alive.. to get things done on MS side.. even in Amiga GUI style");}
       else if (cmd == 'about3'){ term.echo("used to connect to Amiga (FS-UAE) via TCP-COMPORT and relay to MIDI or REMOTE-CONSOLE..");}
        else if (cmd == 'about4'){ term.echo("now using it to connect to vAmigaWeb..AUX/SER/MIDI for Classic Amiga Web usage and using the experience..");}
         else if (cmd == 'about5'){ term.echo("to get it also working for TAWS in the end.. on behalf of the ultimate Amiga Anywhere experience IYKWIM..");}
          else if (cmd == 'about6'){ term.echo("AmiGoDOS for the Web uses JavaScript JQueryTerminal as versatile framework.. with AmiGoDOS syntax/functions..");}
           else if (cmd == 'about7'){ term.echo("a for Web usage simplified AmiGoDOS is just a nice nostalgic label for WIP to keep the momentum going..");}
            else if (cmd == 'about8'){ term.echo("with kind regards.. PTz(Peter Slootbeek)uAH");}
     else if (cmd == 'alias'){ term.echo("WIP: make short version of long commands/args"); }
     else if (cmd == 'assign'){
      switch (CURRENT_MODE){
      case MODE_MIDI_MONITOR:
       term.echo("WIP: assign i.e. 0xFA midi byte to trigger function in the webpage");
       break;
      case MODE_AUX:
      case MODE_AUX_CONSOLE:
      case MODE_DEBUG:
       ADOS_TX_LINE(cmd);
       break;
      default:
       term.echo(cmd + ': Unknown command for chosen MODE');
       break;
      }
     }
     //else if (cmd == 'click')	{ parent.newcli(); }
     else if (cmd == 'a500')    { parent.location.assign("AmiGoDOS.php?amiga=A500");}
     else if (cmd == 'a600')    { parent.location.assign("AmiGoDOS.php?amiga=A600");}
     else if (cmd == 'a1000')   { parent.location.assign("AmiGoDOS.php?amiga=A1000");}
     else if (cmd == 'a2000')   { parent.location.assign("AmiGoDOS.php?amiga=A2000");}
     else if (cmd == 'a3000')   { parent.location.assign("AmiGoDOS.php?amiga=A3000");}
     else if (cmd == 'cdtv')    { parent.location.assign("AmiGoDOS.php?amiga=CDTV");}
     else if (cmd == 'amy')		{ parent.location.assign("AmiGoDOS.php?amiga=Amy");}
     else if (cmd == 'buffy')	{ parent.location.assign("AmiGoDOS.php?amiga=Buffy");}
     else if (cmd == 'claire')	{ parent.location.assign("AmiGoDOS.php?amiga=Claire");}
     else if (cmd == 'daisy')	{ parent.location.assign("AmiGoDOS.php?amiga=Daisy");}
     else if (cmd == 'eva')		{ parent.location.assign("AmiGoDOS.php?amiga=Eva");}
     else if (cmd == 'faith')	{ parent.location.assign("AmiGoDOS.php?amiga=Faith");}
     else if (cmd == 'gwen')	{ parent.location.assign("AmiGoDOS.php?amiga=Gwen");}
     else if (cmd == 'close')	{ parent.close(); }
     else if (cmd == 'cls') 	{ term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
     else if (cmd == 'engage')	{ TS0CA("<?php echo $amiga;?>"); }
     else if (cmd == 'exit')	{ parent.location.assign("../home.php"); }
     else if (cmd == 'halt')  	{ vAmigaWeb_player.stop_emu_view(); }
     //else if (cmd == 'loadwb')	{ parent.location.assign("../taws.php"); }
     else if (cmd == 'logout')	{ parent.location.assign("../access/logout.php"); }
     else if (cmd == 'prompt')	{ term.echo("<?php echo "$prompt";?>"); }
     else if (cmd == 'ftp'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('Available FTP (dummy) commands: ping');}
        else if (command == 'ping') {term.echo('pong');}
        else if (command == 'exit') {term.pop();}
        else { term.echo('unknown FTP command ' + command); }
       },
       { prompt: 'FTP> ', name: 'ftp' }
      );
     }
     else if (cmd == 'midi'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('Available MIDI commands:\n'+
        'start [send MIDI_START byte to the Amiga serial input]\n'+
        'cont [send MIDI_CONTINUE byte to the Amiga serial input]\n'+
        'stop [send MIDI_STOP byte to the Amiga serial input]');}
        else if (command == 'cls'){ term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
        else if (command == 'start') {ADOS_TX_MIDI(command);}
        else if (command == 'cont') {ADOS_TX_MIDI(command);}
        else if (command == 'stop') {ADOS_TX_MIDI(command);}
        else if (command == 'exit') {term.pop();}
        else { term.echo('unknown MIDI command ' + command); }
       },
       { prompt: 'MIDI> ', name: 'midi' }
      );
     }
     else if (cmd == 'vamiga'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('vAmigaWeb commands:\n'+
        'exit [leave the vAmigaWeb Shell]\n'+
        'reset [reset the vAmiga]\n'+
        'toggle_run [run/pause vAmiga]\n'+
        'take_snapshot [save snapshot to local storage]\n'+
        'restore_snapshot [restore snapshot from local storage]');}
        else if (command == 'restore_snapshot') {term.echo('dummy restore_snapshot');}
        else if (command == 'take_snapshot') { vAmigaWeb_player.exec(()=>action('take_snapshot')); }
        //else if (command == 'take_snapshot') { vAmigaWeb_player.exec(()=>alert('Hoi!')); }
        //{term.echo('dummy take_snapshot');}
        else if (command == 'exit')		  { term.pop(); }
        else if (command == 'reset')  	  { vAmigaWeb_player.reset(); }
        else if (command == 'toggle_run') { vAmigaWeb_player.toggle_run(); }
        else { term.echo('unknown vAmigaWeb command ' + command); }
       },
       { prompt: 'vAmigaWeb> ', name: 'vamiga' }
      );
     }
     else {
      // pass_through to AUX
      switch (CURRENT_MODE){
      case MODE_AUX:
      case MODE_AUX_CONSOLE:
      case MODE_DEBUG:
       ADOS_TX_LINE(cmd);
       break;
      default:
       term.echo(cmd + ': Unknown command for chosen MODE');
       break;
      }
     }
     break;
    default:
     if (command_arr.length>>1){ command_arr.splice(0,1);}
     if (cmd == 'click'){ parent.newcli(command_arr[0]); }
     else if (cmd == 'echo')   { term.echo( command_arr.join(" ") ); }
     else if (cmd == 'exit'){ parent.location.assign(command_arr[0]); }
     else if (cmd == 'mode') { CURRENT_MODE=Number(command_arr[0]); term.echo("CURRENT_MODE: " + user_mode[CURRENT_MODE] ); }
     else if (cmd == 'tx'){ ADOS_TX_LINE( command_arr.join(" ") ); }
     else {
      // pass_through to AUX
      switch (CURRENT_MODE){
      case MODE_DEBUG:
      case MODE_AUX:
      case MODE_AUX_CONSOLE:
       ADOS_TX_LINE(command);
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
   prompt: "> ", //if AUX is active we get the prompt from the Amiga console
   <?php echo $init;?>//onInit
   onBlur: function() {	return false; }// prevent loosing focus
  }
 );
});
//window.addEventListener('load', function () { term.echo("It's loaded!") });
</script>
</div>
</div>
<div id="vAmigaWebContainer" style="display: flex;align-items: center;justify-content: left;">
 <div id="container">
  <img id="<?php echo $amiga;?>" style="width:960px; height:633px" src="img/C1084_<?php echo $amiga;?>.gif"
   ontouchstart="touched=true"
   onclick="<?php echo $config_1;?>"
  />
 </div>
</div>
</body>
</html>

