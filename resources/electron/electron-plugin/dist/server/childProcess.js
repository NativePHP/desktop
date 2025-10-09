import { spawn, fork } from "child_process";
const useNodeRuntime = process.env.USE_NODE_RUNTIME === '1';
const [command, ...args] = process.argv.slice(2);
const proc = useNodeRuntime
    ? fork(command, args, {
        stdio: ['pipe', 'pipe', 'pipe', 'ipc'],
        execPath: process.execPath
    })
    : spawn(command, args);
process.parentPort.on('message', (message) => {
    proc.stdin.write(message.data);
});
proc.stdout.on('data', (data) => {
    console.log(data.toString());
});
proc.stderr.on('data', (data) => {
    console.error(data.toString());
});
proc.on('close', (code) => {
    process.exit(code);
});
