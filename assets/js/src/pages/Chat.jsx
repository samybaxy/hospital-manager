import React, { useState, useEffect, useRef } from 'react';
import Card from '../components/Card';
import Button from '../components/Button';

const Chat = () => {
  const [chats, setChats] = useState([]);
  const [activeChat, setActiveChat] = useState(null);
  const [messages, setMessages] = useState([]);
  const [messageInput, setMessageInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const messagesEndRef = useRef(null);

  // Fetch chats on component mount
  useEffect(() => {
    const fetchChats = async () => {
      try {
        const { apiUrl, nonce } = window.hospitalManagerData || {};
        
        if (!apiUrl) {
          throw new Error('API URL not available');
        }
        
        const response = await fetch(`${apiUrl}/chats`, {
          headers: {
            'X-WP-Nonce': nonce,
            'Content-Type': 'application/json'
          }
        });
        
        if (!response.ok) {
          throw new Error('Failed to fetch chats');
        }
        
        const data = await response.json();
        setChats(data);
        setLoading(false);
        
        // Set active chat to the first one if available
        if (data.length > 0) {
          setActiveChat(data[0]);
          fetchMessages(data[0].id);
        }
      } catch (err) {
        console.error('Error fetching chats:', err);
        setError(err.message);
        setLoading(false);
      }
    };
    
    fetchChats();
  }, []);

  // Fetch messages for a specific chat
  const fetchMessages = async (chatId) => {
    try {
      const { apiUrl, nonce } = window.hospitalManagerData || {};
      
      if (!apiUrl) {
        throw new Error('API URL not available');
      }
      
      setLoading(true);
      
      const response = await fetch(`${apiUrl}/chats/${chatId}/messages`, {
        headers: {
          'X-WP-Nonce': nonce,
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch messages');
      }
      
      const data = await response.json();
      setMessages(data);
      setLoading(false);
    } catch (err) {
      console.error('Error fetching messages:', err);
      setError(err.message);
      setLoading(false);
    }
  };

  // Send a new message
  const sendMessage = async (e) => {
    e.preventDefault();
    
    if (!messageInput.trim() || !activeChat) {
      return;
    }
    
    try {
      const { apiUrl, nonce } = window.hospitalManagerData || {};
      
      if (!apiUrl) {
        throw new Error('API URL not available');
      }
      
      const response = await fetch(`${apiUrl}/chats/${activeChat.id}/messages`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': nonce,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message: messageInput
        })
      });
      
      if (!response.ok) {
        throw new Error('Failed to send message');
      }
      
      const newMessage = await response.json();
      setMessages([...messages, newMessage]);
      setMessageInput('');
    } catch (err) {
      console.error('Error sending message:', err);
      setError(err.message);
    }
  };

  // Scroll to bottom of messages when messages change
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  if (loading && !activeChat) {
    return (
      <div className="flex justify-center items-center py-16">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
      </div>
    );
  }
  
  if (error && !activeChat) {
    return (
      <div className="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-4">
        <p>Error: {error}</p>
        <Button 
          variant="primary" 
          className="mt-4"
          onClick={() => window.location.reload()}
        >
          Try Again
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Chat</h1>
      <p className="text-lg text-gray-600">Communication with doctors and staff</p>
      
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {/* Chat list */}
        <div className="md:col-span-1">
          <Card title="Conversations">
            <div className="divide-y divide-gray-200">
              {chats.length > 0 ? (
                chats.map((chat) => (
                  <div 
                    key={chat.id} 
                    className={`p-3 cursor-pointer hover:bg-gray-50 ${activeChat && activeChat.id === chat.id ? 'bg-primary-50' : ''}`}
                    onClick={() => {
                      setActiveChat(chat);
                      fetchMessages(chat.id);
                    }}
                  >
                    <div className="flex items-center">
                      <div className="w-10 h-10 rounded-full bg-primary-600 text-white flex items-center justify-center mr-3">
                        {chat.participant_name.charAt(0)}
                      </div>
                      <div>
                        <div className="font-medium">{chat.participant_name}</div>
                        <div className="text-xs text-gray-500">{chat.last_message_time}</div>
                      </div>
                      {chat.unread_count > 0 && (
                        <div className="ml-auto bg-primary-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                          {chat.unread_count}
                        </div>
                      )}
                    </div>
                  </div>
                ))
              ) : (
                <div className="p-4 text-center text-gray-500">No conversations yet</div>
              )}
            </div>
            <div className="mt-4">
              <Button variant="primary" className="w-full">New Conversation</Button>
            </div>
          </Card>
        </div>
        
        {/* Chat messages */}
        <div className="md:col-span-3">
          <Card>
            {activeChat ? (
              <>
                {/* Chat header */}
                <div className="px-4 py-3 border-b border-gray-200">
                  <div className="flex items-center">
                    <div className="font-medium">{activeChat.participant_name}</div>
                    <div className="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Online</div>
                  </div>
                </div>
                
                {/* Messages */}
                <div className="p-4 h-96 overflow-y-auto">
                  {loading ? (
                    <div className="flex justify-center items-center h-full">
                      <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-primary-600"></div>
                    </div>
                  ) : messages.length > 0 ? (
                    <div className="space-y-4">
                      {messages.map((message) => (
                        <div key={message.id} className={`flex ${message.is_mine ? 'justify-end' : 'justify-start'}`}>
                          <div 
                            className={`max-w-xs md:max-w-md px-4 py-2 rounded-lg ${message.is_mine 
                              ? 'bg-primary-600 text-white' 
                              : 'bg-gray-100 text-gray-900'}`}
                          >
                            <div className="text-sm">{message.message}</div>
                            <div className={`text-xs mt-1 ${message.is_mine ? 'text-primary-100' : 'text-gray-500'}`}>
                              {message.time}
                            </div>
                          </div>
                        </div>
                      ))}
                      <div ref={messagesEndRef} />
                    </div>
                  ) : (
                    <div className="flex justify-center items-center h-full text-gray-500">
                      No messages yet
                    </div>
                  )}
                </div>
                
                {/* Message input */}
                <div className="px-4 py-3 border-t border-gray-200">
                  <form onSubmit={sendMessage} className="flex items-center">
                    <input
                      type="text"
                      className="flex-1 border border-gray-300 rounded-md px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                      placeholder="Type your message..."
                      value={messageInput}
                      onChange={(e) => setMessageInput(e.target.value)}
                    />
                    <Button type="submit" variant="primary" className="ml-2">
                      Send
                    </Button>
                  </form>
                </div>
              </>
            ) : (
              <div className="p-6 text-center text-gray-500">
                Select a conversation or start a new one
              </div>
            )}
          </Card>
        </div>
      </div>
    </div>
  );
};

export default Chat;
