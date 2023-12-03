<?php
 $usermode = "SERIAL_MODE_AUX";
 $server_name = $_ENV['SERVER_NAME'];
 $prompt = '0.SYS:> ';
 //we need to now if AmiGoDOS is called from within the TAWS environment
 if (!isset($_GET['mode'])){$mode=2;}else{$mode=$_GET['mode'];}
 if (!isset($_GET['amiga'])){$amiga='Buffy';}else{$amiga=$_GET['amiga'];}
 //behaviour.. if set then autoboot
 if ( !isset($_GET['autoboot']) ){ $init=''; }
 else{ $init='onInit: function() { TS0CA("'.$amiga.'");},'; }
 //only for serial mode
 if (!isset($_GET['baudrate'])){$baudrate=31250;}else{$baudrate=$_GET['baudrate'];}
 if ( !isset($_GET['boot']) ){
  $boot = '';
  $snapshot="     buttons: [
   {run: true, script:`global_apptitle='$amiga';action('5000ms=>restore_last_snapshot');`}
  ]";
 }else{
  $boot = $_GET['boot'];
  $snapshot="url:'$boot'";
 }
 if (!isset($_ENV['HTTP_REFERER'])){ $REFERRER="EMPTY"; $TAWS_THANKS1="";$TAWS_THANKS2="";}
 else{ $REFERRER=$_ENV['HTTP_REFERER']; }
 //for TAWS detection we use /WB.php from subdir but the original TAWS uses /WB.html from root
 if (str_contains($REFERRER, '/WB.')) {
  $AmiGoTAWS = '<a href=\"https://'.$server_name.'/TS0CA/AmiGoDOS.php\" title=\"Jump to AmiGoDOS (Standalone)..\" target=\"_blank\"><img src=\"Art/TS0CA_Model_MA2RTJE2K.png\" width=\"88\" height=\"88\"></a>';
  $TAWS_THANKS1='Thanks TAWS-developer Michael Rupp! [ https://taws.ch ]\n';
  $TAWS_THANKS2='<a href=\"https://taws.ch\" title=\"Visit the latest TAWS in Switzerland..\" target=\"_blank\"><img src=\"Art/TS0CA_TAWS_THANKS.png\" width=\"88\" height=\"88\"></a>';
 }
 else{
  $AmiGoTAWS = '<a href=\"http://tsoca.'.$server_name.'\" title=\"Visit our AmiGoXPE Salvation Platform (TAWS-based)..\" target=\"_blank\"><img src=\"Art/TS0CA_Model_MA2RTJE2K.png\" width=\"88\" height=\"88\"></a>';
}
  //the emulator files are in the same folder as the run.html let touch=(typeof touched!='undefined')?touched:false;touched=false;
  //you can also enable this and disable the players toolbar (see styles section above)    //let touch=(typeof touched!='undefined')?touched:false;touched=false;

$config_1="
    vAmigaWeb_player.vAmigaWeb_url='./';
    let config={
     touch:false,
     AROS:false,
     x_wait_for_kickstart_injection:true,
     navbar:false,
     wide:true,
     border:false,
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
//we need to store the ports for future reference
//but every pc differs..
//1 relative constant is COM1 which refers to the internal serial port (if available)
//for now the other serial ports via USB should be ignored..
var portserial = null;  // global SERIALAccess object
//if ("serial" in navigator) {
//;(async () => {
// // Initialize the list of available ports with ports on page load.
// const ports = await navigator.serial.getPorts();
// portserial=ports[0]; //how to check success & what port it is?
//})()
//}
var midi = null;  // global MIDIAccess object
var this_frame = null;
var that_frame = null;
var vAmigaWeb0 = null;
var vAmigaWeb1 = null;
var vAmigaWeb2 = null;
const ados_version = 20231203;
const server_name = "<?php echo $server_name;?>"
//var PROMPT_TRIGGERS = ["> ","/N ","S/ ","/K "];
const MODE_AUX = 0;
const MODE_MIDI_MONITOR = 1;
const MODE_AUX_CONSOLE = 2;
const MODE_MIDI_RUNTIME = 3;
const MODE_DEBUG = 4;
const MODE_MIDI_STUDIO = 5;
//experimental mode to communicate between 2 embedded vAmigaWeb instances
const MODE_AMIGONET = 6;
//experimental mode to connect to real amiga via 3wire nullmodem cable
const MODE_NULLMODEM0 = 7;
//experimental mode to communicate between 2 embedded vAmigaWeb instances
const MODE_NULLMODEM1 = 8;
const MODE_NULLMODEM2 = 9;
const MODE_HARDWARE = 10;
//only for processing SYSEX not to be called directly
const MODE_MIDI_STUDIO_SYSEX = 55;
//var CURRENT_MODE = MODE_DEBUG;
var CURRENT_MODE = Number("<?php echo $mode;?>"); //MODE_AUX_CONSOLE;
var AMIGADOS_HELP_MODE = 0;
const help = [];
const user_mode = [
//user_mode.push(
 "<?php echo $usermode;?>" ,
 "SERIAL_MODE_MIDI_MONITOR",
 "SERIAL_MODE_AUX_CONSOLE",
 "SERIAL_MODE_MIDI_RUNTIME",
 "SERIAL_MODE_DEBUG",
 "SERIAL_MODE_MIDI_STUDIO",
 "SERIAL_MODE_AMIGONET",
 "SERIAL_MODE_NULLMODEM0",
 "SERIAL_MODE_NULLMODEM1",
 "SERIAL_MODE_NULLMODEM2",
 "SERIAL_MODE_HARDWARE",
 "SERIAL_MODE_MIDI_STUDIO_SYSEX"
//);
];
//how to receive AUX: serial data from the Amiga formatted in a compact way (trimmed)
let out_buffer="";
let count=0;
let midi_byte_from_amiga = 0;
let midi_running_status_note_on = false;
let midi_running_status_note_off = false;
let midi_sysex = false; //
let midi_use_sysex = false;
const midi_output_id =[];
//const midi_in_event_note_on=[];
//const midi_in_event_note_off=[];
const midi_event_note_on=[];
const midi_event_note_off=[];
const midi_event_polyphonic_aftertouch=[];
const midi_event_control_change=[];
const midi_event_program_change=[]; //1 databyte
const midi_event_channel_aftertouch=[]; //1 databyte
const midi_event_pitch_bend=[];
const midi_event_sysex=[]; // lets try it
//function bindEvent(element, eventName, eventHandler) {
// if (element.addEventListener) {
//  element.addEventListener(eventName, eventHandler, false);
// } else if (element.attachEvent) {
//    element.attachEvent("on" + eventName, eventHandler);
//   }
//}

function INIT_NULLMODEM(){
 switch (CURRENT_MODE) {
 case MODE_NULLMODEM0:
  vAmigaWeb0 = document.getElementById("vAmigaWeb").contentWindow;
  if ("serial" in navigator) {
//	bOpenSerial = document.getElementById("openserial_button");
//	bindEvent(bOpenSerial, "click", async () => {
   const asyncReaderFunc = async () => {
	const port = await navigator.serial.requestPort();
 	await port.open({ baudRate: <?php echo $baudrate;?> });
    portserial = port;
 	while (port.readable) {
  	 const reader = port.readable.getReader();
  	 try {
      while (true) {
       const { value, done } = await reader.read();
       if (done) {
        // |reader| has been canceled & allow the serial port to be closed later.
        break;
       }// Do something with |value|...
       if (value) {
       vAmigaWeb0.postMessage({cmd:"ser:", bytes: value}, "*");
       //term.echo(value);
       //console.log(value);
       }
      }
 	 }catch (error) {
	   // Handle |error|...
       } finally {
    	reader.releaseLock();
       }
 	  }
   }//);
   asyncReaderFunc();
  }
  else {
   term.echo('There is NO HW-Serial support in your browser.');
   //alert("No SERIAL support in your browser.");
  }
 break;
 case MODE_NULLMODEM1:
 that_frame = top.document.getElementById("that_frame");
 vAmigaWeb2 = that_frame.contentWindow.document.getElementById("vAmigaWeb").contentWindow;
 break;
 case MODE_NULLMODEM2:
 this_frame = top.document.getElementById("this_frame");
 vAmigaWeb1 = this_frame.contentWindow.document.getElementById("vAmigaWeb").contentWindow;
 break;
 }
}

function ADOS_PTX_LINE(msg){
var that_frame = document.getElementById("that_frame");
msg="<H2><?php echo $amiga;?> says: "+msg+"</H2>";
 // Send a message to the parent
 window.parent.postMessage(msg, "*");
}
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

window.addEventListener('message', event => {
if(event.data.msg == 'serial_port_out')
{
 switch (CURRENT_MODE) {
 case MODE_AUX:
 case MODE_AUX_CONSOLE:
  let byte_from_amiga=event.data.value;
//  out_buffer+=String.fromCharCode( byte_from_amiga & 0xff );
  switch (byte_from_amiga & 0xff){
  case 0x0f: //skip
   break;
  case 0x0a:
   out_buffer+=String.fromCharCode( byte_from_amiga & 0xff );
   term.echo(out_buffer.trim());
   out_buffer="";
   break;
  default:
   out_buffer+=String.fromCharCode( byte_from_amiga & 0xff );
  }
  if (AMIGADOS_HELP_MODE==1){
   //AmigaDOS switches for AUX CONSOLE cmdline help mode [CMD ?]
   if (out_buffer.slice(-4)=="/N: "){term.set_prompt(out_buffer.trim());out_buffer="";}
   if (out_buffer.slice(-4)=="/S: "){term.set_prompt(out_buffer.trim());out_buffer="";}
   if (out_buffer.slice(-4)=="/K: "){term.set_prompt(out_buffer.trim());out_buffer="";}
  }
  if (out_buffer.slice(-2)=="> "){term.set_prompt(out_buffer.trim());out_buffer="";}
  //Check if EndCLI was called
  if (out_buffer.includes("Process ")==true){
   if (out_buffer.includes(" ending")==true){term.set_prompt("> ");out_buffer="";}
  }
  break;
 case MODE_MIDI_STUDIO_SYSEX:
     midi_event_sysex[midi_event_sysex.length]=midi_byte_from_amiga;
     switch (midi_byte_from_amiga){
      case 0xF7:
       // do something with the buffered sysex_msg
       if (midi_use_sysex == true){
        const output = midi.outputs.get(midi_output_id[0]);
        output.send(midi_event_sysex); // sends the message
       }
       midi_event_sysex.length=0; //just clear the buffer
       midi_sysex = false;
       CURRENT_MODE = MODE_MIDI_STUDIO; //back to normal midi handling
       break;
     }
  break;
 case MODE_MIDI_STUDIO:
  midi_byte_from_amiga=event.data.value & 0xff ;
  // implement RUNNING-STATUS where after a ststusbyte PAIRS
  // of Key/Velocity values need to be processed..
  if (midi_byte_from_amiga > 0x7F){ //STATUS_BYTE
   status_msb_nibble = midi_byte_from_amiga >> 4; //not sure if msb but keep it for now
   status_lsb_nibble = midi_byte_from_amiga & 0xf;
   switch(status_msb_nibble){
    case 0x9: //NOTE_ON
     //turn off running status
     midi_event_note_on[0]=midi_byte_from_amiga;
     midi_running_status_note_on = true;
     midi_running_status_note_off = false;
     break;
    case 0x8: //NOTE_OFF
     midi_event_note_off[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = true;
     break;
    case 0xA: //POLYPHONIC_AFTERTOUCH
     midi_event_polyphonic_aftertouch[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     break;
    case 0xB: //CONTROL_CHANGE
     midi_event_control_change[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     break;
    case 0xC: //PROGRAM_CHANGE
     midi_event_program_change[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     break;
    case 0xD: //CHANNEL_AFTERTOUCH
     midi_event_channel_aftertouch[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     break;
    case 0xE: //PITCH_BEND
     midi_event_pitch_bend[0]=midi_byte_from_amiga;
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     break;
    case 0xF: //SYSEX or REALTIME COMMAND
     midi_running_status_note_on = false;
     midi_running_status_note_off = false;
     switch (status_lsb_nibble){
      case 0x0: // START SYSTEN_EXCLUSIVE BLOCK
       midi_event_sysex[0]=midi_byte_from_amiga;
       midi_sysex = true;
       CURRENT_MODE = MODE_MIDI_STUDIO_SYSEX;
       break;
      //SYSTEM_COMMON_MESSAGES
      case 0x1: //MTC quarter-frame
       break;
      case 0x2: //SPP
       break;
      case 0x3: //SONG_SELECT
       break;
      case 0x4: //UNDEFINED
       break;
      case 0x5: //UNDEFINED
       break;
      case 0x6: //TUNE_REQUEST
       break;
      case 0x7: //EOX End Of eXclusive
       break;
      //SYSTEM_REALTIME_MESSAGES
      case 0x8: //TIMING_CLOCK for now Music-X sending clocks is turned off
       break;
      case 0x9: //UNDEFINED1
       break;
      case 0xA: //START  we also use this from the MIDI Shell
       break;
      case 0xB: //CONTINUE idem..
       break;
      case 0xC: //STOP idem..
       break;
      case 0xD: //UNDEFINED2
       break;
      case 0xE: //ACTIVE_SENSING used to check physical connection status cables
       break;
      case 0xF: //SYSTEM_RESET
       break;
     }
     break;
   }
  }else{
   //0xF0 initiated SYSEX DATA IS HANDLED IN SUB MODE 55
   //if (midi_sysex == true){
   //}
   //else
   //{
   //DATA_BYTE
    if (midi_running_status_note_on == true){
     switch (midi_event_note_on.length){ //NOTE_ON
      case 1: midi_event_note_on[1]=midi_byte_from_amiga; break;
      case 2:
       midi_event_note_on[2]=midi_byte_from_amiga;
       const output = midi.outputs.get(midi_output_id[0]);
       output.send(midi_event_note_on); // sends the message
       midi_event_note_on.length=1; //keep running status
       break;
     }
    }
    if (midi_running_status_note_off == true){
     switch (midi_event_note_off.length){ //NOTE_OFF
      case 1: midi_event_note_off[1]=midi_byte_from_amiga; break;
      case 2:
       midi_event_note_off[2]=0x00;//midi_byte_from_amiga;
       const output = midi.outputs.get(midi_output_id[0]);
       output.send(midi_event_note_off); // sends the message
       midi_event_note_off.length=1;
       break;
     }
    }
    switch (midi_event_polyphonic_aftertouch.length){ //POLYPHONIC_AFTERTOUCH
     case 1: midi_event_polyphonic_aftertouch[1]=midi_byte_from_amiga; break;
     case 2:
      midi_event_polyphonic_aftertouch[2]=midi_byte_from_amiga;
      const output = midi.outputs.get(midi_output_id[0]);
      output.send(midi_event_polyphonic_aftertouch);
      midi_event_polyphonic_aftertouch.length=0;
      break;
    }
    switch (midi_event_control_change.length){ //CONTROL_CHANGE
     case 1: midi_event_control_change[1]=midi_byte_from_amiga; break;
     case 2:
      midi_event_control_change[2]=midi_byte_from_amiga;
      const output = midi.outputs.get(midi_output_id[0]);
      output.send(midi_event_control_change);
      midi_event_control_change.length=0;
      break;
    }
    switch (midi_event_program_change.length){ //PROGRAM_CHANGE
     case 1:
      midi_event_program_change[1]=midi_byte_from_amiga;
      //midi_event_program_change[2]=0x0;
      const output = midi.outputs.get(midi_output_id[0]);
      output.send(midi_event_program_change);
      midi_event_program_change.length=0;
      break;
    }
    switch (midi_event_channel_aftertouch.length){ //CHANNEL_AFTERTOUCH
     case 1:
      midi_event_channel_aftertouch[1]=midi_byte_from_amiga;
      const output = midi.outputs.get(midi_output_id[0]);
      output.send(midi_event_channel_aftertouch);
      midi_event_channel_aftertouch.length=0;
      break;
    }
    switch (midi_event_pitch_bend.length){ //PITCH_BEND
     case 1: midi_event_pitch_bend[1]=midi_byte_from_amiga; break;
     case 2:
      midi_event_pitch_bend[2]=midi_byte_from_amiga;
      const output = midi.outputs.get(midi_output_id[0]);
      output.send(midi_event_pitch_bend);
      midi_event_pitch_bend.length=0;
      break;
    }
   //}
  };
  break;
 case MODE_AMIGONET:
  term.echo("WIP.. this sutosensing mode enables a duplex serial (nullmodem/midi)connection at 31250 BAUD between multiple vAmigaWeb instances..");
  break;
 case MODE_NULLMODEM0:
  let byte_from_amiga0=event.data.value;
  navigator.serial.getPorts().then((ports) => {
  portserial=ports[0];
  });
  if (portserial!=null){
   //term.echo("PORT IS INITIALIZED.. READY FOR ACTION!");
  ;(async () => {
  //const ports = await navigator.serial.getPorts();
  const writer = portserial.writable.getWriter();
  const data = new Uint8Array([byte_from_amiga0]);
  await writer.write(data);
  // Allow the serial port to be closed later.
  writer.releaseLock();
  })()
  //console.log(byte_from_amiga0);
  }else{
   term.echo("PORT IS NOT INITIALIZED!");
  }
  break;
 case MODE_NULLMODEM1:
  //let tx1 = "";
  let byte_from_amiga1=event.data.value;
  //tx1 = String.fromCharCode(byte_from_amiga1 & 0xff) ;
//  let raw_byte_from_amiga1=event.data.value & 0xff ;
   vAmigaWeb2.postMessage({cmd:"ser:", byte: byte_from_amiga1}, "*");
   //vAmigaWeb2.postMessage({cmd:"ser:", text: tx1}, "*");
  //term.echo("WIP.. send data to vAmigaWeb instance 2..");
  break;
 case MODE_NULLMODEM2:
  //let tx2 = "";
  let byte_from_amiga2=event.data.value;
  //tx2 = String.fromCharCode(byte_from_amiga2 & 0xff) ;
   //vAmigaWeb1.postMessage({cmd:"ser:", text: tx2}, "*");
   vAmigaWeb1.postMessage({cmd:"ser:", byte: byte_from_amiga2}, "*");
//  term.echo("WIP.. send data to vAmigaWeb instance 1..");
  break;
 case MODE_MIDI_RUNTIME:
 //disabled but here comes the special modus on demand..
 //Amiga Music-X is OOTB Supported in MODE_MIDI_STUDIO
 //but maybe ScalaMM, Octamed or even MidiPlayer need other settings
 //so a placeholder for future usage..
  term.echo("This mode is not enabled..");
  break;
 case MODE_MIDI_MONITOR:
  midi_byte_from_amiga=event.data.value & 0xff ;
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
// try figure out how the parent acts
//else //if(event.data.msg == 'message')
//{
//console.log(`Received message: ${event.data}`);
//term.echo("this is a test of event: "+ event.data +","+event.type);
//}
});
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

function onMIDISuccess( midiAccess ) {
  term.echo( "WebMIDI API ready!" );
  midi = midiAccess;  // store in the global (in real usage, would probably keep in an object instance)
 // autostart the first MIDI output
 startMIDIOutput(midi,0);
}
function onMIDIFailure(msg) {
  term.echo( "Failed to get MIDI access - " + msg );
}
function onMIDISYSEXSuccess( midiAccess ) {
  term.echo( "WebMIDI SYSEX API ready!" );
  midi = midiAccess;  // store in the global (in real usage, would probably keep in an object instance)
 // autostart the first MIDI output
 startMIDIOutput(midi,0);
}
function onMIDISYSEXFailure(msg) {
  term.echo( "Failed to get MIDI SYSEX access - " + msg );
}
function GET_MIDI_INPUTS( midiAccess ) {
  for (const entry of midiAccess.inputs) {
    const input = entry[1];
    term.echo(
      `Input port [type:'${input.type}']` +
        ` id:'${input.id}'` +
        ` manufacturer:'${input.manufacturer}'` +
        ` name:'${input.name}'` +
        ` version:'${input.version}'`
    );
  }
}
function GET_MIDI_OUTPUTS( midiAccess ) {
  for (const entry of midiAccess.outputs) {
    const output = entry[1];
    term.echo(
      `Output port [type:'${output.type}'] id:'${output.id}' manufacturer:'${output.manufacturer}' name:'${output.name}' version:'${output.version}'`
    );
  }
}

function apiMIDI_INS_OUTS( midiAccess ) {
 GET_MIDI_OUTPUTS(midiAccess);
 GET_MIDI_INPUTS(midiAccess);
//for (const entry of midiAccess.inputs) {
//  const input = entry[1];
//  term.echo(input.id);
//}

}

function apiMIDI_INIT( midiAccess ) {
		// request MIDI access
		if(navigator.requestMIDIAccess){
			navigator.requestMIDIAccess({sysex: false}).then(onMIDISuccess, onMIDIFailure);
		}
		else {
			alert("No MIDI support in your browser.");
		}

}

function onMIDIMessage(event) {
 switch (CURRENT_MODE) {
 case MODE_MIDI_MONITOR:
  let str = `MIDI message received at timestamp ${event.timeStamp}[${event.data.length} bytes]: `;
  for (const character of event.data) {
    str += `0x${character.toString(16)} `;
  }
  term.echo(str);
  break;
 case MODE_MIDI_RUNTIME:
  break;
 case MODE_MIDI_STUDIO:
   let vAmigaWeb_window = document.getElementById("vAmigaWeb").contentWindow;
   for (const character of event.data){
   vAmigaWeb_window.postMessage({cmd:"ser:", byte: character}, "*");
   //vAmigaWeb_window.postMessage({cmd:"ser:", bytes: event.data}, "*");
   }
   break;
 }
}

function startLoggingMIDIInput(midiAccess, indexOfPort) {
  midiAccess.inputs.forEach((entry) => {
    entry.onmidimessage = onMIDIMessage;
  });
}

function startMIDIOutput(midiAccess, indexOfPort) {
 for (const entry of midiAccess.outputs) {
  const output = entry[1];
  //term.echo("Midi-Out_Open:".output.id);
  midi_output_id.push(output.id);
  output.open();
  //break;
 }
}

function START_SERIAL( serAccess ) {
  if ("serial" in navigator) {
  // The Web Serial API is supported.
   navigator.serial.getPorts().then((ports) => {
   // Initialize the list of available ports with `ports` on page load.
   });
   term.echo('HARDWARE SERIAL support enabled.');
   // Prompt user to select any serial port. major bug when using await navigator.serial.requestPort();
   const port = navigator.serial.requestPort();
  }
  else {
   term.echo('No HARDWARE SERIAL support in your browser.');
   //alert("No SERIAL support in your browser.");
  }

}

// local functions when not connected to AUX:
function ADOS_ALIAS(key, value){
}

function ADOS_ASSIGN(key, value){
}

function TS0CA(modelID='Buffy') {
 switch (CURRENT_MODE) {
 case MODE_MIDI_MONITOR:
 case MODE_MIDI_RUNTIME:
// case MODE_MIDI_STUDIO:
 case MODE_MIDI_STUDIO_SYSEX:
		// request MIDI access
		if(navigator.requestMIDIAccess){
			navigator.requestMIDIAccess({sysex: true}).then(onMIDISYSEXSuccess, onMIDISYSEXFailure);
		}
		else {
			alert("No MIDI SYSEX support in your browser.");
		}
//  navigator.requestMIDIAccess().then( onMIDISuccess, onMIDIFailure );
  break;
 }
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
     " alias, assign, cls, echo, exit, help, prompt, version\n"+
     " clear, click, close, engage, logout, mode, lic\n"+
     "Available Amiga's by Name: amy, buffy, claire, daisy, eva, faith, gwen\n"+
     "Available Amiga's by Type: a500, a600, a1000, a2000, a3000, cdtv\n"+
     "Command Shells: nullmodem, serial, midi, vamiga, ftp(dummy)"); }
     else if (cmd == 'about1'){ term.echo("AmiGoDOS dialect is my amiga-ish syntax flavoured devshell originated in Delphi7 Pascal in 2002..");}
      else if (cmd == 'about2'){ term.echo("tried to keep the AmigaDOS syntax somehow alive.. to get things done on MS side.. even in Amiga GUI style");}
       else if (cmd == 'about3'){ term.echo("used to connect to Amiga (FS-UAE) via TCP-COMPORT and relay to MIDI or REMOTE-CONSOLE..");}
        else if (cmd == 'about4'){ term.echo("now using it to connect to vAmigaWeb..AUX/SER/MIDI for Classic Amiga Web usage and using the experience..");}
         else if (cmd == 'about5'){ term.echo("IT also works for our version of TAWS.. on behalf of the ultimate Amiga Anywhere experience IYKWIM..");}
          else if (cmd == 'about6'){ term.echo("AmiGoDOS for the Web uses JavaScript JQueryTerminal as versatile framework.. with AmiGoDOS syntax/functions..");}
           else if (cmd == 'about7'){ term.echo("a for Web usage simplified AmiGoDOS is just a nice nostalgic label for WIP to keep the momentum going..");}
            else if (cmd == 'about8'){ term.echo("with kind regards.. PTz(Peter Slootbeek)uAH");}
     else if (cmd == 'thx'){
      term.echo("Thank you for beta-testing AmiGoDOS..");
      break;
     }
     else if (cmd == 'lic'){
     new Audio('Art/Y0_UP.mp3').play();
     term.echo(
     "AmiGoDOS (TS0CA) licenses, attributions & more..\n"+
     "This just-for-the-fun-damen-tal-edu-art-zen-project utilises the following frameworks:\n"+
     "TAWS by Michael Rupp! [ https://taws.ch ]\n"+
     "vAmigaWeb (GPL-3.0) by Mithrendal [ https://github.com/vAmigaWeb/vAmigaWeb ]\n"+
     "Jquery.Terminal (MIT) by Jakub T. Jankiewicz [ https://github.com/jcubic/jquery.terminal ]\n"+
     "AmiGoDOS (TS0CA) by PTz(Peter Slootbeek)uAH [ https://github.com/PTz0uAH/AmiGoDOS ]\n"+
     "You may use AmiGoDOS for free to maintain & preserve \"The Spirit Of Commodore Amiga\"..\n"+
     "any usage outside (TS0CA) scope may need explicit oral consent..\n"+
     "\"Yo..up!\" tune performed by \"Maartje2K& The BigDreamers\" for PTz(SL02TBE2K-SYSTEMS)uAH..\n"+
     "\"Sunny\" logo (re)designed by Youp for PTz(SL02TBE2K-SYSTEMS)uAH..\n"+
     "other gfx/art created/provided by \"Brother Gregorius\" [ https://www.facebook.com/genetic.wisdom ]\n"+
     "All trademarks belong to their respective owners!"
     );
     return $('<img src=\"Art/SL02TBE2K-SYSTEMS_logo.png\" width=\"64\" height=\"88\">'+
     '<img src=\"Art/AmiGoDOS_logo.png\" width=\"88\" height=\"88\">'+
     '<?php echo "$AmiGoTAWS";?>'+
     '<a href=\"https://github.com/vAmigaWeb\" title=\"Click to visit the vAmigaWeb support site on GitHub\" target=\"_blank\"><img src=\"Art/TS0CA_vAmigaWeb_THANKS.png\" width=\"88\" height=\"88\"></a>'+
     '<a href=\"https://taws.ch\" title=\"Visit the latest TAWS in Switzerland..\" target=\"_blank\"><img src=\"Art/TS0CA_TAWS_THANKS.png\" width=\"88\" height=\"88\"></a>'+
     '<a href=\"https://terminal.jcubic.pl/\" title=\"Click if you wish to visit the JQuery.Terminal support site in Poland\" target=\"_blank\"><img src=\"Art/TS0CA_JQueryTerminal_THANKS.png\" width=\"88\" height=\"88\"></a>'+
     '<a href=\"https://www.facebook.com/groups/612005812580097/\" title=\"AmiGoDOS is endorsed by the Admins of 47PAINFBAT.. Support (y)our Troops!\" target=\"_blank\"><img src=\"Art/TS0CA_670613165_TANKS.png\" width=\"88\" height=\"88\"></a>'
     );
     }
     else if (cmd == 'version'){term.echo(ados_version);}
     else if (cmd == 'alias'){ term.echo("WIP: make short version of long commands/args"); }
     else if (cmd == 'assign'){
      switch (CURRENT_MODE){
      case MODE_MIDI_STUDIO:
      case MODE_MIDI_MONITOR:
      case MODE_MIDI_RUNTIME:
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
     else if (cmd == 'A500')    { parent.location.assign("AmiGoDOS.php?amiga=A500&autoboot");}
     else if (cmd == 'a600')    { parent.location.assign("AmiGoDOS.php?amiga=A600");}
     else if (cmd == 'A600')    { parent.location.assign("AmiGoDOS.php?amiga=A600&autoboot");}
     else if (cmd == 'a1000')   { parent.location.assign("AmiGoDOS.php?amiga=A1000");}
     else if (cmd == 'A1000')   { parent.location.assign("AmiGoDOS.php?amiga=A1000&autoboot");}
     else if (cmd == 'a2000')   { parent.location.assign("AmiGoDOS.php?amiga=A2000");}
     else if (cmd == 'A2000')   { parent.location.assign("AmiGoDOS.php?amiga=A2000&autoboot");}
     else if (cmd == 'a3000')   { parent.location.assign("AmiGoDOS.php?amiga=A3000");}
     else if (cmd == 'A3000')   { parent.location.assign("AmiGoDOS.php?amiga=A3000&autoboot");}
     else if (cmd == 'cdtv')    { parent.location.assign("AmiGoDOS.php?amiga=CDTV");}
     else if (cmd == 'CDTV')    { parent.location.assign("AmiGoDOS.php?amiga=CDTV&autoboot");}
     // use Amy for MIDI stuff like Music-X, Octamed, Scala etc..
     else if (cmd == 'amy')		{ parent.location.assign("AmiGoDOS.php?amiga=Amy");}
     else if (cmd == 'Amy')		{ parent.location.assign("AmiGoDOS.php?amiga=Amy&mode=5&autoboot");}
     else if (cmd == 'buffy')	{ parent.location.assign("AmiGoDOS.php?amiga=Buffy");}
     else if (cmd == 'Buffy')	{ parent.location.assign("AmiGoDOS.php?amiga=Buffy&autoboot");}
     else if (cmd == 'claire')	{ parent.location.assign("AmiGoDOS.php?amiga=Claire");}
     else if (cmd == 'Claire')	{ parent.location.assign("AmiGoDOS.php?amiga=Claire&autoboot");}
     else if (cmd == 'daisy')	{ parent.location.assign("AmiGoDOS.php?amiga=Daisy");}
     else if (cmd == 'Daisy')	{ parent.location.assign("AmiGoDOS.php?amiga=Daisy&autoboot");}
     else if (cmd == 'eva')		{ parent.location.assign("AmiGoDOS.php?amiga=Eva");}
     else if (cmd == 'Eva')		{ parent.location.assign("AmiGoDOS.php?amiga=Eva&autoboot");}
     else if (cmd == 'faith')	{ parent.location.assign("AmiGoDOS.php?amiga=Faith");}
     else if (cmd == 'Faith')	{ parent.location.assign("AmiGoDOS.php?amiga=Faith&autoboot");}
     else if (cmd == 'gwen')	{ parent.location.assign("AmiGoDOS.php?amiga=Gwen");}
     else if (cmd == 'Gwen')	{ parent.location.assign("AmiGoDOS.php?amiga=Gwen&autoboot");}
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
     else if (cmd == 'nullmodem'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('Available NULLMODEM commands:\n'+
        'exit [leave NULLMODEM mode]\n'+
        'init [start NULLMODEM Inputs/Outputs] depending on MODE_SERIAL_NULLMODEMx\n'+
        'cls [clear shell]');}
        else if (command == 'cls'){ term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
        else if (command == 'init') {INIT_NULLMODEM();}
        else if (command == 'menu') {
    	return $('<button id="openserial_button">Open Serial Port</button>');
        }
        else if (command == 'exit') {term.pop();}
        else { term.echo('unknown NULLMODEM command ' + command); }
       },
       { prompt: 'NULLMODEM> ', name: 'ser' }
      );
     }
     /*else if (cmd == 'serial'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('Available SERIAL commands:\n'+
        'exit [leave SERIAL mode]\n'+
        'init [start SERIAL Inputs/Outputs]\n'+
        'cls [clear shell]');}
        else if (command == 'cls'){ term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
        else if (command == 'init') {START_SERIAL(serial);}
        else if (command == 'exit') {term.pop();}
        else { term.echo('unknown SER command ' + command); }
       },
       { prompt: 'SER> ', name: 'ser' }
      );
     }*/
     else if (cmd == 'midi'){
      term.push(
       function(command, term) {
        if (command == 'help') {term.echo('Available MIDI commands:\n'+
        'exit [leave MIDI mode]\n'+
        'info [show MIDI Inputs/Outputs]\n'+
        'midi_in_open [monitor data received from Inputs]\n'+
        'start [send MIDI_START byte to the Amiga serial input]\n'+
        'cont [send MIDI_CONTINUE byte to the Amiga serial input]\n'+
        'stop [send MIDI_STOP byte to the Amiga serial input]');}
        else if (command == 'cls'){ term.clear(); term.echo("AmiGoDOS - Developer Shell [" + user_mode[CURRENT_MODE] + "]"); }
        else if (command == 'start') {count=0; ADOS_TX_MIDI(command);}
        else if (command == 'cont') {ADOS_TX_MIDI(command);}
        else if (command == 'stop') {ADOS_TX_MIDI(command);}
        else if (command == 'info') {apiMIDI_INS_OUTS(midi);}
        else if (command == 'init') {apiMIDI_INIT(midi);}
        else if (command == 'midi_in_open') {startLoggingMIDIInput(midi,0);}
        //else if (command == 'midi_out_open') {startMIDIOutput(midi,0);}
//        else if (command == 'midi_out_close') {startMIDIOutput(midi,0);}
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
     else if (cmd == 'ptx'){ ADOS_PTX_LINE( command_arr.join(" ") ); }
     else {
      // pass_through to AUX
      switch (CURRENT_MODE){
      case MODE_DEBUG:
      case MODE_AUX:
      case MODE_AUX_CONSOLE:
       if (command.length>2){
        if ( command.slice(-2)==" ?" ){AMIGADOS_HELP_MODE=1;}
        else {AMIGADOS_HELP_MODE=0;}
       }
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
   onBlur: function() {	return false; }// prevent loosing focus   ontouchstart="touched=true"
  }
 );
});
</script>
</div>
</div>
<div id="vAmigaWebContainer" style="display: flex;align-items: center;justify-content: left;">
 <div id="container">
  <img id="<?php echo $amiga;?>" style="width:960px; height:633px" src="Art/C1084_<?php echo $amiga;?>.gif"
   onclick="<?php echo $config_1;?>"
  />
 </div>
</div>
</body>
</html>
