OCHA AI Chat Module
===================

This module provides a "chat" functionality to perform queries against
ReliefWeb documents via AI (large language models).

This implements a RAG (retrieval augmented generation) approach:

1. Get ReliefWeb documents so that we have a limited scope for the question.
2. Extract texts from the documents and their attachments
3. Split the texts into passages (smaller texts)
4. Generate embeddings for the passages
5. Store the passages and their embeddings in a vector database
6. Uppon query, generate the embedding for the question.
7. Retrieve relevant passages from the vector store using a cosine similarity
   between the question embedding and the passage embeddings.
8. Generate a prompt with the relevant passages, asking the AI to only answer
   based on the information in those passages.
9. Pass the prompt to a Large Language Model to get an answer to the question

TODO
----

**Plugins**

- [ ] Add amazon bedrock completion and embedding plugins.
- [ ] Emulate relevant endpoints in the completion and embedding services.
- [ ] Make the plugins configurable.
- [ ] Create a plugin type for the source documents. One plugin would be to
      get documents from ReliefWeb but other plugins could be used to retrieve
      information from elsewhere?

**Improve answer**

- [ ] Use mininum score maybe something like 1.5?
- [ ] Filter on the length of the extract (ex: at least 4 words)?
- [ ] Add some code to fix glaring problems with the extracted text (see
      ReliefWeb "fix PDF" feature).
- [ ] Increase size of the chunks, maybe 3-4 sentences with 1-2 overlap? Or
      defined max length in characters.
- [ ] Increase number of results?
- [ ] Refine prompt, maybe separate each extract with some prefix like "Fact:"
      so that the AI understands they are separate pieces of information.
- [ ] Generate list of references at the end of the answer.
