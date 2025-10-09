import {spawn, fork} from "child_process";

const useNodeRuntime = process.env.USE_NODE_RUNTIME === '1';
const [command, ...args] = process.argv.slice(2);

// If we need to start the process using the bundled nodejs runtime we need
// to use utilityProcess.fork. Otherwise, we can use utilityProcess.spawn
const proc = useNodeRuntime
    ? fork(command, args, {
        stdio: ['pipe', 'pipe', 'pipe', 'ipc'],
        execPath: process.execPath
    })
    : spawn(command, args);

process.parentPort.on('message', (message) => {
    proc.stdin.write(message.data)
});

// Handle normal output
proc.stdout.on('data', (data) => {
    console.log(data.toString());
});

// Handle error output
proc.stderr.on('data', (data) => {
    console.error(data.toString());
});

// Handle process exit
proc.on('close', (code) => {
    process.exit(code)
});
