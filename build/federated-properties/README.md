This provides a quick and automated way to configure a test system for a 'Federated Properties' enabled Wikibase instance

### Prerequisites

We use [ansible](https://docs.ansible.com/ansible/latest/index.html) to manipulate remote servers via SSH - essentially automating what you'd otherwise do "by hand" in a step-by-step approach. Make sure to have version >=2.8, e.g. by installing via `pip`:
```
$ pip install ansible

$ ansible --version
ansible 2.9.6
```

You need to be in possession of an SSH private key for which there is a associated user that is authorized to perform the operations.

### Inventory

The file `inventory.yml` contains a set of two differrent hosts, which can be used as targets for the test system setup:
 * `wikidata-federated-properties.wikidata-dev.eqiad.wmflabs` - the project's official cloud VPS test instance on https://horizon.wikimedia.org/
 * `federated-properties.vm` - a virtual machine on your computer

### Use your own test system on a VM
#### Create

If you want to use a virtual machine to run the test system on your computer, please make sure to have both `vagrant` and `VirtualBox` installed:
```
$ sudo apt install vagrant virtualbox-qt

# creates a Debian VM with 1024MB memory and 2 CPUs.
$ cd extensions/Wikibase/build/federated-properties/vagrant
$ vagrant up
```

#### Configure /etc/hosts
In order to reach your newly created VM both via http and ssh, add a line to your `/etc/hosts` file:
```
192.168.100.42 federated-properties.vm
```
You should now be able to ping your VM using the host name, instead of its IP address:
```
$ ping federated-properties.vm
PING federated-properties.vm (192.168.100.42) 56(84) bytes of data.
64 bytes from federated-properties.vm (192.168.100.42): icmp_seq=1 ttl=64 time=0.259 ms
64 bytes from federated-properties.vm (192.168.100.42): icmp_seq=2 ttl=64 time=0.160 ms
```

#### Configure ssh

Additionally, add a section to your `~/.ssh/config` file:
```
Host federated-properties.vm
  User vagrant
  IdentityFile <YOUR-PATH-TO-WIKIBASE>/build/federated-properties/vagrant/.vagrant/machines/default/virtualbox/private_key
```
You should now be able to ssh into your VM without providing a username or an identy file:
```
$ ssh federated-properties.vm

[Long Debian Welcome message]

vagrant@federated-properties:~$ exit
```

#### Run ansible

```sh
$ cd extensions/Wikibase/build/federated-properties
$ ansible-playbook fedProps.yml --limit federated-properties.vm
```
Once the setup process has completed, you can access your newly installed Wikibase test system via http://federated-properties.vm:8080.


### Use a cloud VPS instance

Set up your VPS instance on https://horizon.wikimedia.org and a web proxy to reach it from the internet, then:
```sh
$ cd extensions/Wikibase/build/federated-properties
$ ansible-playbook fedProps.yml --limit wikidata-federated-properties.wikidata-dev.eqiad.wmflabs
```

Once the setup process has completed, you can access the newly installed Wikibase test system via https://wikidata-federated-properties.wmflabs.org.


### Cleanup

The `cleanup.yml` playbook removes most of the changes that the setup has caused:

```sh
$ cd extensions/Wikibase/build/federated-properties

# cleanup the VM
$ ansible-playbook cleanup.yml --limit federated-properties.vm

# cleanup the cloud VPS instance
ansible-playbook cleanup.yml --limit wikidata-federated-properties.wikidata-dev.eqiad.wmflabs

# cleanup both simultaneously
ansible-playbook cleanup.yml
```
